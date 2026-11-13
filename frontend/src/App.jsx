import { useEffect, useState } from 'react'
import BookingPage from './pages/BookingPage'
import CustomerAuthPage from './pages/CustomerAuthPage'
import HomePage from './pages/HomePage'
import OwnerPage from './pages/OwnerPage'
import RoleSelectPage from './pages/RoleSelectPage'
import { apiRequest } from './utils/api'

function App() {
  // صفحات: 'role-select' | 'customer-login' | 'customer-register' | 'home' | 'booking' | 'owner'
  const [page, setPage] = useState('role-select')
  const [services, setServices] = useState([])
  const [servicesError, setServicesError] = useState('')
  const [loggedInCustomer, setLoggedInCustomer] = useState(null)

  // دریافت لیست خدمات از بک‌اند
  function fetchServices() {
    setServicesError('')
    apiRequest('/api/services')
      .then((data) => {
        setServices(Array.isArray(data) ? data : [])
      })
      .catch((err) => {
        setServicesError(err.message || 'خطا در دریافت لیست خدمات')
      })
  }

  useEffect(() => {
    fetchServices()
  }, [])

  function handleLogoClick() {
    if (page === 'booking') {
      setPage('home')
    } else {
      setPage('role-select')
    }
  }

  function handleAuthSuccess(customer) {
    setLoggedInCustomer(customer)
    fetchServices()
    setPage('home')
  }

  function handleOpenBooking() {
    if (loggedInCustomer?.id) {
      apiRequest(`/api/customers/${loggedInCustomer.id}`)
        .then((data) => {
          if (data?.customer) {
            setLoggedInCustomer(data.customer)
          }
        })
        .catch(() => {})
    }
    setPage('booking')
  }

  return (
    <div className="page" dir="rtl">
      <header className="header">
        <button type="button" className="secondary-btn logo-btn" onClick={handleLogoClick}>
          پیرایش عارف
        </button>
      </header>

      <main className="main">
        {page === 'role-select' && (
          <RoleSelectPage
            onSelectCustomer={() => setPage('customer-login')}
            onSelectOwner={() => setPage('owner')}
          />
        )}

        {page === 'customer-login' && (
          <CustomerAuthPage
            mode="login"
            onSuccess={handleAuthSuccess}
            onGoToRegister={() => setPage('customer-register')}
            onBack={() => setPage('role-select')}
          />
        )}

        {page === 'customer-register' && (
          <CustomerAuthPage
            mode="register"
            onSuccess={handleAuthSuccess}
            onGoToLogin={() => setPage('customer-login')}
            onBack={() => setPage('role-select')}
          />
        )}

        {page === 'home' && (
          <HomePage
            services={services}
            error={servicesError}
            customer={loggedInCustomer}
            onOpenBooking={handleOpenBooking}
            onBack={() => setPage('role-select')}
          />
        )}

        {page === 'booking' && (
          <BookingPage
            customer={loggedInCustomer}
            services={services}
            onBack={() => setPage('home')}
          />
        )}

        {page === 'owner' && <OwnerPage onBack={() => setPage('role-select')} />}
      </main>
    </div>
  )
}

export default App

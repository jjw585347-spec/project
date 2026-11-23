import { useEffect, useState } from 'react'
import { Navigate, Route, Routes, useLocation, useNavigate } from 'react-router-dom'
import BookingPage from './pages/BookingPage'
import CustomerAuthPage from './pages/CustomerAuthPage'
import HomePage from './pages/HomePage'
import OwnerPage from './pages/OwnerPage'
import RoleSelectPage from './pages/RoleSelectPage'
import { apiRequest } from './utils/api'

const CUSTOMER_STORAGE_KEY = 'barbershop_logged_in_customer'

function loadStoredCustomer() {
  try {
    const raw = sessionStorage.getItem(CUSTOMER_STORAGE_KEY)
    return raw ? JSON.parse(raw) : null
  } catch {
    return null
  }
}

function App() {
  const [services, setServices] = useState([])
  const [servicesError, setServicesError] = useState('')
  const [loggedInCustomer, setLoggedInCustomer] = useState(loadStoredCustomer)
  const navigate = useNavigate()
  const location = useLocation()

  function updateCustomer(customer) {
    setLoggedInCustomer(customer)
    try {
      if (customer) {
        sessionStorage.setItem(CUSTOMER_STORAGE_KEY, JSON.stringify(customer))
      } else {
        sessionStorage.removeItem(CUSTOMER_STORAGE_KEY)
        sessionStorage.removeItem('barbershop_booking_page_state')
      }
    } catch {
      // نادیده گرفتن خطای احتمالی مرورگر در حالت Private
    }
  }

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

  function goToRoleSelect() {
    if (location.pathname !== '/') {
      navigate('/')
    }
  }

  function handleLogoClick() {
    if (location.pathname === '/booking') {
      navigate('/services')
    } else {
      goToRoleSelect()
    }
  }

  function handleAuthSuccess(customer) {
    try {
      sessionStorage.removeItem('barbershop_booking_page_state')
    } catch {
      //
    }
    updateCustomer(customer)
    fetchServices()
    navigate('/services')
  }

  function handleOpenBooking() {
    if (loggedInCustomer?.id) {
      apiRequest(`/api/customers/${loggedInCustomer.id}`)
        .then((data) => {
          if (data?.customer) {
            updateCustomer(data.customer)
          }
        })
        .catch(() => {})
    }
    navigate('/booking')
  }

  return (
    <div className="page" dir="rtl">
      <header className="header">
        <button type="button" className="secondary-btn logo-btn" onClick={handleLogoClick}>
          پیرایش عارف
        </button>
      </header>

      <main className="main">
        <Routes>
          <Route
            path="/"
            element={
              <RoleSelectPage
                onSelectCustomer={() => navigate('/login')}
                onSelectOwner={() => navigate('/owner')}
              />
            }
          />
          <Route
            path="/login"
            element={
              <CustomerAuthPage
                mode="login"
                onSuccess={handleAuthSuccess}
                onGoToRegister={() => navigate('/register')}
                onBack={goToRoleSelect}
              />
            }
          />
          <Route
            path="/register"
            element={
              <CustomerAuthPage
                mode="register"
                onSuccess={handleAuthSuccess}
                onGoToLogin={() => navigate('/login')}
                onBack={goToRoleSelect}
              />
            }
          />
          <Route
            path="/services"
            element={
              <HomePage
                services={services}
                error={servicesError}
                customer={loggedInCustomer}
                onOpenBooking={handleOpenBooking}
                onBack={goToRoleSelect}
              />
            }
          />
          <Route
            path="/booking"
            element={
              <BookingPage
                customer={loggedInCustomer}
                services={services}
                onBack={() => navigate('/services')}
              />
            }
          />
          <Route path="/owner" element={<OwnerPage onBack={goToRoleSelect} />} />
          <Route path="*" element={<Navigate to="/" replace />} />
        </Routes>
      </main>
    </div>
  )
}

export default App

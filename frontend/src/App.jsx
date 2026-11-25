import { useEffect, useState } from 'react'
import { Navigate, Route, Routes, useLocation, useNavigate } from 'react-router-dom'
import AdminLoginPage from './pages/AdminLoginPage'
import BookingPage from './pages/BookingPage'
import CustomerAuthPage from './pages/CustomerAuthPage'
import HomePage from './pages/HomePage'
import OwnerPage from './pages/OwnerPage'
import RoleSelectPage from './pages/RoleSelectPage'
import { apiRequest } from './utils/api'

const CUSTOMER_STORAGE_KEY = 'barbershop_logged_in_customer'
const ADMIN_TOKEN_STORAGE_KEY = 'barbershop_admin_token'

function loadStoredCustomer() {
  try {
    const raw = sessionStorage.getItem(CUSTOMER_STORAGE_KEY)
    return raw ? JSON.parse(raw) : null
  } catch {
    return null
  }
}

function loadStoredAdminToken() {
  try {
    return sessionStorage.getItem(ADMIN_TOKEN_STORAGE_KEY) || ''
  } catch {
    return ''
  }
}

function App() {
  const [services, setServices] = useState([])
  const [servicesError, setServicesError] = useState('')
  const [loggedInCustomer, setLoggedInCustomer] = useState(loadStoredCustomer)
  const [adminToken, setAdminToken] = useState(loadStoredAdminToken)
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

  function updateAdminToken(token) {
    const normalized = token || ''
    setAdminToken(normalized)
    try {
      if (normalized) {
        sessionStorage.setItem(ADMIN_TOKEN_STORAGE_KEY, normalized)
      } else {
        sessionStorage.removeItem(ADMIN_TOKEN_STORAGE_KEY)
      }
    } catch {
      //
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

  useEffect(() => {
    if (location.pathname === '/services' || location.pathname === '/booking') {
      fetchServices()
    }
  }, [location.pathname])

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

  function handleAdminLoginSuccess(token) {
    updateAdminToken(token)
    navigate('/owner')
  }

  function handleAdminLogout() {
    updateAdminToken('')
    navigate('/owner/login')
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
                onSelectOwner={() => navigate(adminToken ? '/owner' : '/owner/login')}
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
          <Route
            path="/owner/login"
            element={
              adminToken ? (
                <Navigate to="/owner" replace />
              ) : (
                <AdminLoginPage onSuccess={handleAdminLoginSuccess} onBack={goToRoleSelect} />
              )
            }
          />
          <Route
            path="/owner"
            element={
              adminToken ? (
                <OwnerPage
                  adminToken={adminToken}
                  onUnauthorized={handleAdminLogout}
                  onLogout={handleAdminLogout}
                  onBack={goToRoleSelect}
                  onServicesChanged={fetchServices}
                />
              ) : (
                <Navigate to="/owner/login" replace />
              )
            }
          />
          <Route path="*" element={<Navigate to="/" replace />} />
        </Routes>
      </main>
    </div>
  )
}

export default App

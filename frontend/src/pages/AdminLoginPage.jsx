import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { apiRequest } from '../utils/api'
import { toEnglishDigits } from '../utils/persian'

export default function AdminLoginPage({ onSuccess, onBack }) {
  const [phone, setPhone] = useState('')
  const [password, setPassword] = useState('')
  const [fieldErrors, setFieldErrors] = useState({})
  const [error, setError] = useState('')
  const [submitting, setSubmitting] = useState(false)
  const navigate = useNavigate()

  function clearFieldError(field) {
    setFieldErrors((prev) => {
      if (!prev[field]) return prev
      const next = { ...prev }
      delete next[field]
      return next
    })
  }

  function handleBackClick() {
    if (onBack) {
      onBack()
    } else {
      navigate('/')
    }
  }

  async function handleSubmit(e) {
    e.preventDefault()
    setError('')
    setFieldErrors({})

    const normalizedPhone = toEnglishDigits(phone).trim()
    const normalizedPassword = toEnglishDigits(password).trim()

    setSubmitting(true)
    try {
      const data = await apiRequest('/api/admin/login', {
        method: 'POST',
        body: JSON.stringify({
          phone: normalizedPhone,
          password: normalizedPassword,
        }),
      })
      onSuccess(data.token)
    } catch (err) {
      if (err.errors && Object.keys(err.errors).length > 0) {
        setFieldErrors(err.errors)
      } else {
        setError(err.message || 'خطا در ورود به پنل مدیریت')
      }
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <div className="booking-container">
      <h1 className="title">ورود به پنل مدیریت</h1>

      <form className="booking-form" onSubmit={handleSubmit} noValidate>
        <div className="form-group">
          <label htmlFor="adminPhone">شماره موبایل مدیر</label>
          <input
            id="adminPhone"
            type="tel"
            className={fieldErrors.phone ? 'input-invalid' : ''}
            aria-invalid={Boolean(fieldErrors.phone)}
            value={phone}
            onChange={(e) => {
              setPhone(e.target.value)
              clearFieldError('phone')
            }}
            placeholder="مثلاً 09120000000"
          />
          {fieldErrors.phone && (
            <p className="field-error" role="alert">
              {fieldErrors.phone}
            </p>
          )}
        </div>

        <div className="form-group">
          <label htmlFor="adminPassword">رمز عبور</label>
          <input
            id="adminPassword"
            type="password"
            className={fieldErrors.password ? 'input-invalid' : ''}
            aria-invalid={Boolean(fieldErrors.password)}
            value={password}
            onChange={(e) => {
              setPassword(e.target.value)
              clearFieldError('password')
            }}
            placeholder="رمز عبور مدیر را وارد کنید"
          />
          {fieldErrors.password && (
            <p className="field-error" role="alert">
              {fieldErrors.password}
            </p>
          )}
        </div>

        {error && <p className="error">{error}</p>}

        <div className="auth-actions">
          <button type="submit" className="primary-btn full-btn" disabled={submitting}>
            {submitting ? 'در حال ورود...' : 'ورود به پنل مدیریت'}
          </button>
        </div>
      </form>

      <div className="page-bottom-nav">
        <button type="button" className="secondary-btn" onClick={handleBackClick}>
          برگشت به صفحه اصلی
        </button>
      </div>
    </div>
  )
}

import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { apiRequest } from '../utils/api'
import { toEnglishDigits } from '../utils/persian'

export default function CustomerAuthPage({
  mode = 'login',
  onSuccess,
  onGoToRegister,
  onGoToLogin,
  onBack,
}) {
  const [name, setName] = useState('')
  const [phone, setPhone] = useState('')
  const [password, setPassword] = useState('')
  const [fieldErrors, setFieldErrors] = useState({})
  const [error, setError] = useState('')
  const [submitting, setSubmitting] = useState(false)
  const navigate = useNavigate()

  const isLogin = mode === 'login'

  function clearFieldError(field) {
    setFieldErrors((prev) => {
      if (!prev[field]) return prev
      const next = { ...prev }
      delete next[field]
      return next
    })
  }

  function handleGoToRegister() {
    if (onGoToRegister) {
      onGoToRegister()
    } else {
      navigate('/register')
    }
  }

  function handleGoToLogin() {
    if (onGoToLogin) {
      onGoToLogin()
    } else {
      navigate('/login')
    }
  }

  function handleBackClick() {
    if (onBack) {
      onBack()
    } else {
      navigate('/')
    }
  }

  // فقط اجازه ورود اعداد (حداکثر ۴ رقم) در فیلد رمز عبور
  function handlePasswordChange(e) {
    const raw = toEnglishDigits(e.target.value)
    const digitsOnly = raw.replace(/\D/g, '').slice(0, 4)
    setPassword(digitsOnly)
    clearFieldError('password')
  }

  async function handleSubmit(e) {
    e.preventDefault()
    setError('')
    setFieldErrors({})

    const trimmedName = name.trim()
    const normalizedPhone = toEnglishDigits(phone).trim()
    const normalizedPassword = toEnglishDigits(password).trim()

    setSubmitting(true)
    try {
      const endpoint = isLogin ? '/api/customers/login' : '/api/customers/register'
      const data = await apiRequest(endpoint, {
        method: 'POST',
        body: JSON.stringify({
          name: trimmedName,
          phone: normalizedPhone,
          password: normalizedPassword,
        }),
      })
      onSuccess(data.customer)
    } catch (err) {
      if (err.errors && Object.keys(err.errors).length > 0) {
        setFieldErrors(err.errors)
      } else {
        setError(err.message || (isLogin ? 'خطا در ورود به حساب' : 'خطا در ایجاد حساب'))
      }
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <div className="booking-container">
      <h1 className="title">{isLogin ? 'ورود مشتری' : 'ایجاد حساب مشتری'}</h1>

      <form className="booking-form" onSubmit={handleSubmit} noValidate>
        <div className="form-group">
          <label htmlFor="authName">نام و نام خانوادگی</label>
          <input
            id="authName"
            type="text"
            className={fieldErrors.name ? 'input-invalid' : ''}
            aria-invalid={Boolean(fieldErrors.name)}
            value={name}
            onChange={(e) => {
              setName(e.target.value)
              clearFieldError('name')
            }}
            placeholder={
              isLogin ? 'نامی که قبلاً ثبت کرده‌اید را وارد کنید' : 'نام خود را وارد کنید'
            }
          />
          {fieldErrors.name && (
            <p className="field-error" role="alert">
              {fieldErrors.name}
            </p>
          )}
        </div>

        <div className="form-group">
          <label htmlFor="authPhone">شماره موبایل</label>
          <input
            id="authPhone"
            type="tel"
            className={fieldErrors.phone ? 'input-invalid' : ''}
            aria-invalid={Boolean(fieldErrors.phone)}
            value={phone}
            onChange={(e) => {
              setPhone(e.target.value)
              clearFieldError('phone')
            }}
            placeholder="مثلاً 09123456789"
          />
          {fieldErrors.phone && (
            <p className="field-error" role="alert">
              {fieldErrors.phone}
            </p>
          )}
        </div>

        <div className="form-group">
          <label htmlFor="authPassword">رمز عبور ۴ رقمی (فقط عدد)</label>
          <input
            id="authPassword"
            type="password"
            inputMode="numeric"
            maxLength={4}
            className={fieldErrors.password ? 'input-invalid' : ''}
            aria-invalid={Boolean(fieldErrors.password)}
            value={password}
            onChange={handlePasswordChange}
            placeholder="یک رمز ۴ رقمی عددی (مثلاً 1234)"
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
            {submitting ? 'در حال بررسی...' : 'ثبت'}
          </button>

          {isLogin ? (
            <button type="button" className="secondary-btn full-btn" onClick={handleGoToRegister}>
              ایجاد حساب
            </button>
          ) : (
            <button type="button" className="secondary-btn full-btn" onClick={handleGoToLogin}>
              ورود به حساب قبلی
            </button>
          )}
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

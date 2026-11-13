import { useState } from 'react'
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
  const [error, setError] = useState('')
  const [submitting, setSubmitting] = useState(false)

  const isLogin = mode === 'login'

  // فقط اجازه ورود اعداد (حداکثر ۴ رقم) در فیلد رمز عبور
  function handlePasswordChange(e) {
    const raw = toEnglishDigits(e.target.value)
    const digitsOnly = raw.replace(/\D/g, '').slice(0, 4)
    setPassword(digitsOnly)
  }

  async function handleSubmit(e) {
    e.preventDefault()
    setError('')

    const trimmedName = name.trim()
    const normalizedPhone = toEnglishDigits(phone).trim()
    const normalizedPassword = toEnglishDigits(password).trim()

    if (!trimmedName || !normalizedPhone || !normalizedPassword) {
      setError('لطفاً نام، شماره موبایل و رمز عبور ۴ رقمی خود را کامل وارد کنید.')
      return
    }

    if (!/^\d{4}$/.test(normalizedPassword)) {
      setError('رمز عبور باید فقط و فقط شامل ۴ رقم عدد باشد (مثلاً 1234).')
      return
    }

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
      setError(err.message || (isLogin ? 'خطا در ورود به حساب' : 'خطا در ایجاد حساب'))
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <div className="booking-container">
      <h1 className="title">{isLogin ? 'ورود مشتری' : 'ایجاد حساب مشتری'}</h1>

      <form className="booking-form" onSubmit={handleSubmit}>
        <div className="form-group">
          <label htmlFor="authName">نام و نام خانوادگی</label>
          <input
            id="authName"
            type="text"
            maxLength={40}
            value={name}
            onChange={(e) => setName(e.target.value)}
            placeholder={
              isLogin ? 'نامی که قبلاً ثبت کرده‌اید را وارد کنید' : 'نام خود را وارد کنید'
            }
            required
          />
        </div>

        <div className="form-group">
          <label htmlFor="authPhone">شماره موبایل</label>
          <input
            id="authPhone"
            type="tel"
            value={phone}
            onChange={(e) => setPhone(e.target.value)}
            placeholder="مثلاً 09123456789"
            required
          />
        </div>

        <div className="form-group">
          <label htmlFor="authPassword">رمز عبور ۴ رقمی (فقط عدد)</label>
          <input
            id="authPassword"
            type="password"
            inputMode="numeric"
            maxLength={4}
            value={password}
            onChange={handlePasswordChange}
            placeholder="یک رمز ۴ رقمی عددی (مثلاً 1234)"
            required
          />
        </div>

        {error && <p className="error">{error}</p>}

        <div className="auth-actions">
          <button type="submit" className="primary-btn full-btn" disabled={submitting}>
            {submitting ? 'در حال بررسی...' : 'ثبت'}
          </button>

          {isLogin ? (
            <button type="button" className="secondary-btn full-btn" onClick={onGoToRegister}>
              ایجاد حساب
            </button>
          ) : (
            <button type="button" className="secondary-btn full-btn" onClick={onGoToLogin}>
              ورود به حساب قبلی
            </button>
          )}
        </div>
      </form>

      <div className="page-bottom-nav">
        <button type="button" className="secondary-btn" onClick={onBack}>
          برگشت به صفحه اصلی
        </button>
      </div>
    </div>
  )
}

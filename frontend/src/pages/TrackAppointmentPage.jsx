import { useEffect, useMemo, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { apiRequest } from '../utils/api'
import { formatGregorianToJalaliDisplay } from '../utils/jalali'
import { formatPrice, toPersianDigits } from '../utils/persian'

const TRACKING_PREFILL_KEY = 'barbershop_tracking_prefill'

function loadTrackingPrefill() {
  try {
    const raw = sessionStorage.getItem(TRACKING_PREFILL_KEY)
    return raw ? JSON.parse(raw) : null
  } catch {
    return null
  }
}

export default function TrackAppointmentPage({ customer, onBack }) {
  const navigate = useNavigate()
  const [prefill] = useState(loadTrackingPrefill)

  const [customerPhone, setCustomerPhone] = useState(
    prefill?.customerPhone || customer?.phone || ''
  )
  const [trackingCode, setTrackingCode] = useState(prefill?.trackingCode || '')

  const [fieldErrors, setFieldErrors] = useState({})
  const [lookupError, setLookupError] = useState('')
  const [cancelFeedback, setCancelFeedback] = useState('')
  const [cancelError, setCancelError] = useState('')
  const [loading, setLoading] = useState(false)
  const [cancelling, setCancelling] = useState(false)
  const [appointment, setAppointment] = useState(null)

  function handleBackClick() {
    if (onBack) {
      onBack()
    } else {
      navigate('/')
    }
  }

  function clearFieldError(field) {
    setFieldErrors((prev) => {
      if (!prev[field]) return prev
      const next = { ...prev }
      delete next[field]
      return next
    })
  }

  async function lookupAppointment(phoneVal, codeVal) {
    setLookupError('')
    setCancelFeedback('')
    setCancelError('')
    setFieldErrors({})
    setLoading(true)

    try {
      const data = await apiRequest('/api/appointments/track', {
        method: 'POST',
        body: JSON.stringify({
          customer_phone: phoneVal.trim(),
          tracking_code: codeVal.trim(),
        }),
      })
      setAppointment(data.appointment || null)
    } catch (err) {
      setAppointment(null)
      if (err.errors && Object.keys(err.errors).length > 0) {
        setFieldErrors(err.errors)
      } else {
        setLookupError(
          err.message || 'نوبتی با این شماره موبایل و کد پیگیری یافت نشد.'
        )
      }
    } finally {
      setLoading(false)
    }
  }

  // اگر کاربر از صفحه خلاصه ثبت نوبت مستقیم روی «پیگیری یا لغو این نوبت» کلیک کرده باشد، خودکار جستجو شود
  useEffect(() => {
    if (prefill?.customerPhone && prefill?.trackingCode) {
      try {
        sessionStorage.removeItem(TRACKING_PREFILL_KEY)
      } catch {
        //
      }
      lookupAppointment(prefill.customerPhone, prefill.trackingCode)
    }
  }, [])

  function handleSearchSubmit(e) {
    e.preventDefault()
    lookupAppointment(customerPhone, trackingCode)
  }

  async function handleCancelAppointment() {
    if (!appointment) return
    setCancelFeedback('')
    setCancelError('')
    setCancelling(true)

    try {
      const data = await apiRequest('/api/appointments/track/cancel', {
        method: 'POST',
        body: JSON.stringify({
          customer_phone: customerPhone.trim() || appointment.customer_phone,
          tracking_code: trackingCode.trim() || appointment.tracking_code,
        }),
      })
      setAppointment(data.appointment || null)
      setCancelFeedback(data.message || 'نوبت شما با موفقیت لغو شد.')
    } catch (err) {
      if (err.data?.appointment) {
        setAppointment(err.data.appointment)
      }
      setCancelError(err.message || 'خطا در لغو نوبت')
    } finally {
      setCancelling(false)
    }
  }

  const serviceNames = useMemo(() => {
    if (!appointment) return ''
    if (Array.isArray(appointment.services) && appointment.services.length > 0) {
      return appointment.services.map((s) => s.name).join('، ')
    }
    return appointment.service?.name || '-'
  }, [appointment])

  const totalPrice = useMemo(() => {
    if (!appointment) return 0
    if (appointment.total_price !== undefined && appointment.total_price !== null) {
      return Number(appointment.total_price)
    }
    if (Array.isArray(appointment.services) && appointment.services.length > 0) {
      return appointment.services.reduce((sum, s) => sum + (Number(s.price) || 0), 0)
    }
    return Number(appointment.service?.price) || 0
  }, [appointment])

  const totalDuration = useMemo(() => {
    if (!appointment) return 30
    return Number(appointment.total_duration) || Number(appointment.service?.duration) || 30
  }, [appointment])

  const isCancelled = appointment?.status === 'cancelled'
  const canCancel = Boolean(appointment?.can_customer_cancel) && !isCancelled
  const deadlineHours = Number(appointment?.cancel_deadline_hours) || 2
  const cancelBlockReason =
    appointment?.cancel_block_reason ||
    (!canCancel && !isCancelled
      ? `امکان لغو نوبت فقط تا ${toPersianDigits(deadlineHours)} ساعت قبل از زمان نوبت وجود دارد و مهلت لغو این نوبت به پایان رسیده است.`
      : '')

  return (
    <div className="booking-container">
      <h1 className="title">پیگیری نوبت</h1>
      <p className="subtitle">
        شماره موبایل و کد پیگیری ۶ رقمی خود را وارد کنید تا جزئیات نوبت خود را ببینید یا در صورت نیاز لغو کنید.
      </p>

      <form className="booking-form" onSubmit={handleSearchSubmit} noValidate>
        <div className="form-group">
          <label htmlFor="trackPhone">شماره موبایل</label>
          <input
            id="trackPhone"
            type="tel"
            className={fieldErrors.customer_phone ? 'input-invalid' : ''}
            aria-invalid={Boolean(fieldErrors.customer_phone)}
            value={customerPhone}
            onChange={(e) => {
              setCustomerPhone(e.target.value)
              clearFieldError('customer_phone')
            }}
            placeholder="مثلاً 09123456789"
          />
          {fieldErrors.customer_phone && (
            <p className="field-error" role="alert">
              {fieldErrors.customer_phone}
            </p>
          )}
        </div>

        <div className="form-group">
          <label htmlFor="trackCode">کد پیگیری ۶ رقمی</label>
          <input
            id="trackCode"
            type="text"
            inputMode="numeric"
            maxLength={6}
            className={fieldErrors.tracking_code ? 'input-invalid' : ''}
            aria-invalid={Boolean(fieldErrors.tracking_code)}
            value={trackingCode}
            onChange={(e) => {
              setTrackingCode(e.target.value)
              clearFieldError('tracking_code')
            }}
            placeholder="مثلاً ۱۲۳۴۵۶"
          />
          {fieldErrors.tracking_code && (
            <p className="field-error" role="alert">
              {fieldErrors.tracking_code}
            </p>
          )}
        </div>

        {lookupError && (
          <p className="error" role="alert">
            {lookupError}
          </p>
        )}

        <div className="form-actions">
          <button type="submit" className="primary-btn" disabled={loading}>
            {loading ? 'در حال جستجو...' : 'مشاهده نوبت'}
          </button>
        </div>
      </form>

      {appointment && (
        <div className="tracked-appointment-card" role="region" aria-label="اطلاعات نوبت پیگیری‌شده">
          <div className="tracked-header">
            <h2 className="tracked-title">مشخصات نوبت شما</h2>
            {isCancelled ? (
              <span className="cancelled-badge">لغو شده</span>
            ) : (
              <span className="active-badge">فعال</span>
            )}
          </div>

          <div className="summary-row confirm-detail-row">
            <span>کد پیگیری:</span>
            <strong>{toPersianDigits(appointment.tracking_code)}</strong>
          </div>
          <div className="summary-row confirm-detail-row">
            <span>نام مشتری:</span>
            <strong>{appointment.customer_name}</strong>
          </div>
          <div className="summary-row confirm-detail-row">
            <span>شماره موبایل:</span>
            <strong>{toPersianDigits(appointment.customer_phone)}</strong>
          </div>
          <div className="summary-row confirm-detail-row">
            <span>خدمت‌ها:</span>
            <strong>{serviceNames}</strong>
          </div>
          <div className="summary-row confirm-detail-row">
            <span>هزینه‌ی کل:</span>
            <strong>{formatPrice(totalPrice)} تومان</strong>
          </div>
          <div className="summary-row confirm-detail-row">
            <span>مدت کل:</span>
            <strong>{toPersianDigits(totalDuration)} دقیقه</strong>
          </div>
          <div className="summary-row confirm-detail-row">
            <span>روز نوبت:</span>
            <strong>{formatGregorianToJalaliDisplay(appointment.appointment_date)}</strong>
          </div>
          <div className="summary-row confirm-detail-row">
            <span>ساعت نوبت:</span>
            <strong>{toPersianDigits(appointment.appointment_time)}</strong>
          </div>

          {cancelFeedback && (
            <p className="admin-feedback-success" style={{ marginTop: '16px' }} role="status">
              {cancelFeedback}
            </p>
          )}

          {cancelError && (
            <p className="error" role="alert">
              {cancelError}
            </p>
          )}

          {/* بخش لغو نوبت توسط مشتری طبق قانون R9 */}
          <div className="tracked-cancel-section">
            <button
              type="button"
              className="cancel-btn tracked-cancel-btn"
              disabled={!canCancel || cancelling}
              onClick={handleCancelAppointment}
            >
              {cancelling
                ? 'در حال لغو نوبت...'
                : isCancelled
                  ? 'این نوبت لغو شده است'
                  : 'لغو نوبت'}
            </button>

            {!isCancelled && !canCancel && cancelBlockReason && (
              <p className="cancel-disabled-reason" role="note" data-testid="cancel-disabled-reason">
                {toPersianDigits(cancelBlockReason)}
              </p>
            )}

            {canCancel && (
              <p className="cancel-deadline-hint">
                شما تا {toPersianDigits(deadlineHours)} ساعت قبل از زمان شروع نوبت امکان لغو آنلاین این نوبت را دارید.
              </p>
            )}
          </div>
        </div>
      )}

      <div className="page-bottom-nav booking-bottom-links">
        {customer && (
          <button type="button" className="secondary-btn" onClick={() => navigate('/booking')}>
            رفتن به صفحه رزرو نوبت
          </button>
        )}
        <button type="button" className="secondary-btn" onClick={handleBackClick}>
          برگشت
        </button>
      </div>
    </div>
  )
}

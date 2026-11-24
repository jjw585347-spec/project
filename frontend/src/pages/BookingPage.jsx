import { useEffect, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import JalaliDatePicker from '../components/JalaliDatePicker'
import TimeSlotPicker from '../components/TimeSlotPicker'
import { apiRequest } from '../utils/api'
import { formatGregorianToJalaliDisplay } from '../utils/jalali'
import { formatPrice, TIME_SLOTS, toPersianDigits } from '../utils/persian'

const BOOKING_STATE_KEY = 'barbershop_booking_page_state'

function loadStoredBookingState() {
  try {
    const raw = sessionStorage.getItem(BOOKING_STATE_KEY)
    return raw ? JSON.parse(raw) : null
  } catch {
    return null
  }
}

export default function BookingPage({ customer, services, onBack }) {
  const navigate = useNavigate()
  const [stored] = useState(loadStoredBookingState)

  function handleBackClick() {
    if (onBack) {
      onBack()
    } else {
      navigate('/services')
    }
  }

  const [customerName, setCustomerName] = useState(
    stored?.customerName !== undefined ? stored.customerName : customer?.name || ''
  )
  const [customerPhone, setCustomerPhone] = useState(
    stored?.customerPhone !== undefined ? stored.customerPhone : customer?.phone || ''
  )
  const [serviceId, setServiceId] = useState(
    stored?.serviceId || (services[0]?.id ? String(services[0].id) : '')
  )
  const [appointmentDate, setAppointmentDate] = useState(stored?.appointmentDate || '')
  const [appointmentTime, setAppointmentTime] = useState(stored?.appointmentTime || '10:00')
  const [bookedSlots, setBookedSlots] = useState([])
  const [fieldErrors, setFieldErrors] = useState({})
  const [formError, setFormError] = useState('')
  const [submitting, setSubmitting] = useState(false)
  const [savedAppointment, setSavedAppointment] = useState(stored?.savedAppointment || null)

  function clearFieldError(field) {
    setFieldErrors((prev) => {
      if (!prev[field]) return prev
      const next = { ...prev }
      delete next[field]
      return next
    })
  }

  // پر شدن خودکار نام و شماره از حساب کاربری مشتری (اگر هنوز در فرم خالی باشد)
  useEffect(() => {
    if (customer) {
      setCustomerName((prev) => prev || customer.name || '')
      setCustomerPhone((prev) => prev || customer.phone || '')
    }
  }, [customer])

  // ذخیره وضعیت فرم و کادر سبز خلاصه نوبت در sessionStorage تا با رفرش صفحه نپرد
  useEffect(() => {
    try {
      sessionStorage.setItem(
        BOOKING_STATE_KEY,
        JSON.stringify({
          customerName,
          customerPhone,
          serviceId,
          appointmentDate,
          appointmentTime,
          savedAppointment,
        })
      )
    } catch {
      // نادیده گرفتن خطای احتمالی storage
    }
  }, [
    customerName,
    customerPhone,
    serviceId,
    appointmentDate,
    appointmentTime,
    savedAppointment,
  ])

  useEffect(() => {
    if (!serviceId && services.length > 0) {
      setServiceId(String(services[0].id))
    }
  }, [services, serviceId])

  // دریافت ساعت‌های رزروشده در روز انتخابی
  useEffect(() => {
    if (!appointmentDate) {
      setBookedSlots([])
      return
    }

    apiRequest(`/api/appointments/booked-slots?date=${encodeURIComponent(appointmentDate)}`)
      .then((data) => {
        const activeTimes = Array.isArray(data.booked_slots) ? data.booked_slots : []
        setBookedSlots(activeTimes)

        // اگر ساعت فعلی رزرو شده است، اولین ساعت آزاد را انتخاب کن
        if (activeTimes.includes(appointmentTime)) {
          const firstFree = TIME_SLOTS.find((slot) => !activeTimes.includes(slot))
          if (firstFree) {
            setAppointmentTime(firstFree)
          }
        }
      })
      .catch(() => {
        setBookedSlots([])
      })
  }, [appointmentDate])

  async function handleSubmit(e) {
    e.preventDefault()
    setFormError('')
    setFieldErrors({})
    setSavedAppointment(null)

    setSubmitting(true)
    try {
      const data = await apiRequest('/api/appointments', {
        method: 'POST',
        body: JSON.stringify({
          customer_id: customer?.id || null,
          customer_name: customerName.trim(),
          customer_phone: customerPhone.trim(),
          service_id: serviceId ? Number(serviceId) : null,
          appointment_date: appointmentDate,
          appointment_time: appointmentTime,
        }),
      })
      setSavedAppointment(data.appointment)
      setBookedSlots((prev) =>
        prev.includes(appointmentTime) ? prev : [...prev, appointmentTime]
      )
    } catch (err) {
      if (err.errors && Object.keys(err.errors).length > 0) {
        setFieldErrors(err.errors)
        if (err.errors.customer_id) {
          setFormError(err.errors.customer_id)
        }
      } else {
        setFormError(err.message || 'خطا در ثبت نوبت')
      }
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <div className="booking-container">
      <h1 className="title">رزرو نوبت</h1>

      <form className="booking-form" onSubmit={handleSubmit} noValidate>
        <div className="form-group">
          <label htmlFor="customerName">نام و نام خانوادگی</label>
          <input
            id="customerName"
            type="text"
            className={fieldErrors.customer_name ? 'input-invalid' : ''}
            aria-invalid={Boolean(fieldErrors.customer_name)}
            value={customerName}
            onChange={(e) => {
              setCustomerName(e.target.value)
              clearFieldError('customer_name')
            }}
            placeholder="نام خود را وارد کنید"
          />
          {fieldErrors.customer_name && (
            <p className="field-error" role="alert">
              {fieldErrors.customer_name}
            </p>
          )}
        </div>

        <div className="form-group">
          <label htmlFor="customerPhone">شماره موبایل</label>
          <input
            id="customerPhone"
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
          <label htmlFor="serviceId">انتخاب خدمت</label>
          <select
            id="serviceId"
            className={fieldErrors.service_id ? 'input-invalid' : ''}
            aria-invalid={Boolean(fieldErrors.service_id)}
            value={serviceId}
            onChange={(e) => {
              setServiceId(e.target.value)
              clearFieldError('service_id')
            }}
          >
            {services.map((service) => (
              <option key={service.id} value={service.id}>
                {service.name} ({formatPrice(service.price)} تومان -{' '}
                {Number(service.duration).toLocaleString('fa-IR')} دقیقه)
              </option>
            ))}
          </select>
          {fieldErrors.service_id && (
            <p className="field-error" role="alert">
              {fieldErrors.service_id}
            </p>
          )}
        </div>

        <div className="form-group">
          <label htmlFor="appointmentDate">انتخاب روز (شمسی)</label>
          <JalaliDatePicker
            id="appointmentDate"
            invalid={Boolean(fieldErrors.appointment_date)}
            value={appointmentDate}
            onChange={(val) => {
              setAppointmentDate(val)
              clearFieldError('appointment_date')
            }}
          />
          {fieldErrors.appointment_date && (
            <p className="field-error" role="alert">
              {fieldErrors.appointment_date}
            </p>
          )}
        </div>

        <div className="form-group">
          <label>انتخاب ساعت</label>
          <TimeSlotPicker
            value={appointmentTime}
            onChange={(val) => {
              setAppointmentTime(val)
              clearFieldError('appointment_time')
            }}
            bookedSlots={bookedSlots}
          />
          {fieldErrors.appointment_time && (
            <p className="field-error" role="alert">
              {fieldErrors.appointment_time}
            </p>
          )}
        </div>

        {formError && <p className="error">{formError}</p>}

        <div className="form-actions">
          <button type="submit" className="primary-btn" disabled={submitting}>
            {submitting ? 'در حال ثبت...' : 'ثبت نوبت'}
          </button>
        </div>
      </form>

      {savedAppointment && (
        <div className="success-box">
          <h2 className="success-title">نوبت شما با موفقیت ثبت شد!</h2>
          <div className="summary-row">
            <span>نام مشتری:</span>
            <strong>{savedAppointment.customer_name}</strong>
          </div>
          <div className="summary-row">
            <span>شماره موبایل:</span>
            <strong>{toPersianDigits(savedAppointment.customer_phone)}</strong>
          </div>
          <div className="summary-row">
            <span>خدمت انتخاب‌شده:</span>
            <strong>{savedAppointment.service?.name}</strong>
          </div>
          <div className="summary-row">
            <span>روز نوبت:</span>
            <strong>{formatGregorianToJalaliDisplay(savedAppointment.appointment_date)}</strong>
          </div>
          <div className="summary-row">
            <span>ساعت نوبت:</span>
            <strong>{toPersianDigits(savedAppointment.appointment_time)}</strong>
          </div>
        </div>
      )}

      <div className="page-bottom-nav">
        <button type="button" className="secondary-btn" onClick={handleBackClick}>
          برگشت به صفحه خدمات
        </button>
      </div>
    </div>
  )
}

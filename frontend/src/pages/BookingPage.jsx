import { useEffect, useState } from 'react'
import JalaliDatePicker from '../components/JalaliDatePicker'
import TimeSlotPicker from '../components/TimeSlotPicker'
import { apiRequest } from '../utils/api'
import { formatGregorianToJalaliDisplay } from '../utils/jalali'
import { formatPrice, TIME_SLOTS, toPersianDigits } from '../utils/persian'

export default function BookingPage({ customer, services, onBack }) {
  const [customerName, setCustomerName] = useState(customer?.name || '')
  const [customerPhone, setCustomerPhone] = useState(customer?.phone || '')
  const [serviceId, setServiceId] = useState(services[0]?.id ? String(services[0].id) : '')
  const [appointmentDate, setAppointmentDate] = useState('')
  const [appointmentTime, setAppointmentTime] = useState('10:00')
  const [bookedSlots, setBookedSlots] = useState([])
  const [formError, setFormError] = useState('')
  const [submitting, setSubmitting] = useState(false)
  const [savedAppointment, setSavedAppointment] = useState(null)

  // پر شدن خودکار نام و شماره از حساب کاربری مشتری (همراه با امکان ویرایش)
  useEffect(() => {
    if (customer) {
      setCustomerName(customer.name || '')
      setCustomerPhone(customer.phone || '')
    }
  }, [customer])

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

    apiRequest(`/api/appointments?date=${encodeURIComponent(appointmentDate)}`)
      .then((data) => {
        const activeTimes = (data.appointments || [])
          .filter((item) => item.status !== 'cancelled')
          .map((item) => item.appointment_time)
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
    setSavedAppointment(null)

    if (!customer?.id) {
      setFormError('برای ثبت نوبت ابتدا باید وارد حساب کاربری خود شوید.')
      return
    }

    if (!customerName.trim() || !customerPhone.trim()) {
      setFormError('لطفاً نام و شماره موبایل را کامل وارد کنید.')
      return
    }

    if (!serviceId || !appointmentDate || !appointmentTime) {
      setFormError('لطفاً خدمت، روز و ساعت نوبت را انتخاب کنید.')
      return
    }

    setSubmitting(true)
    try {
      const data = await apiRequest('/api/appointments', {
        method: 'POST',
        body: JSON.stringify({
          customer_id: customer.id,
          customer_name: customerName.trim(),
          customer_phone: customerPhone.trim(),
          service_id: Number(serviceId),
          appointment_date: appointmentDate,
          appointment_time: appointmentTime,
        }),
      })
      setSavedAppointment(data.appointment)
      setBookedSlots((prev) =>
        prev.includes(appointmentTime) ? prev : [...prev, appointmentTime]
      )
    } catch (err) {
      setFormError(err.message || 'خطا در ثبت نوبت')
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <div className="booking-container">
      <h1 className="title">رزرو نوبت</h1>

      <form className="booking-form" onSubmit={handleSubmit}>
        <div className="form-group">
          <label htmlFor="customerName">نام و نام خانوادگی</label>
          <input
            id="customerName"
            type="text"
            maxLength={40}
            value={customerName}
            onChange={(e) => setCustomerName(e.target.value)}
            placeholder="نام خود را وارد کنید"
            required
          />
        </div>

        <div className="form-group">
          <label htmlFor="customerPhone">شماره موبایل</label>
          <input
            id="customerPhone"
            type="tel"
            value={customerPhone}
            onChange={(e) => setCustomerPhone(e.target.value)}
            placeholder="مثلاً 09123456789"
            required
          />
        </div>

        <div className="form-group">
          <label htmlFor="serviceId">انتخاب خدمت</label>
          <select
            id="serviceId"
            value={serviceId}
            onChange={(e) => setServiceId(e.target.value)}
            required
          >
            {services.map((service) => (
              <option key={service.id} value={service.id}>
                {service.name} ({formatPrice(service.price)} تومان -{' '}
                {Number(service.duration).toLocaleString('fa-IR')} دقیقه)
              </option>
            ))}
          </select>
        </div>

        <div className="form-group">
          <label htmlFor="appointmentDate">انتخاب روز (شمسی)</label>
          <JalaliDatePicker
            id="appointmentDate"
            value={appointmentDate}
            onChange={setAppointmentDate}
          />
        </div>

        <div className="form-group">
          <label>انتخاب ساعت</label>
          <TimeSlotPicker
            value={appointmentTime}
            onChange={setAppointmentTime}
            bookedSlots={bookedSlots}
          />
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
        <button type="button" className="secondary-btn" onClick={onBack}>
          برگشت به صفحه خدمات
        </button>
      </div>
    </div>
  )
}

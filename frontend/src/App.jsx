import { useEffect, useState } from 'react'

// نمایش قیمت با جداکننده هزارگان و اعداد فارسی
function formatPrice(price) {
  return Number(price).toLocaleString('fa-IR')
}

// ساخت لیست ساعت‌ها از 10 تا 20 با فاصله 30 دقیقه‌ای
function generateTimeSlots() {
  const slots = []
  for (let hour = 10; hour <= 20; hour++) {
    const h = String(hour).padStart(2, '0')
    slots.push(`${h}:00`)
    if (hour < 20) {
      slots.push(`${h}:30`)
    }
  }
  return slots
}

const TIME_SLOTS = generateTimeSlots()

function App() {
  const [services, setServices] = useState([])
  const [error, setError] = useState('')
  const [page, setPage] = useState('home') // 'home' | 'booking'

  // وضعیت فرم رزرو
  const [customerName, setCustomerName] = useState('')
  const [customerPhone, setCustomerPhone] = useState('')
  const [serviceId, setServiceId] = useState('')
  const [appointmentDate, setAppointmentDate] = useState('')
  const [appointmentTime, setAppointmentTime] = useState('10:00')
  const [formError, setFormError] = useState('')
  const [submitting, setSubmitting] = useState(false)
  const [savedAppointment, setSavedAppointment] = useState(null)

  useEffect(() => {
    // گرفتن لیست خدمات از بک اند
    fetch('/api/services')
      .then((response) => response.json())
      .then((data) => {
        setServices(data)
        if (data.length > 0) {
          setServiceId(String(data[0].id))
        }
      })
      .catch(() => setError('خطا در گرفتن لیست خدمات'))
  }, [])

  function handleOpenBooking() {
    setFormError('')
    setSavedAppointment(null)
    setPage('booking')
  }

  function handleSubmit(e) {
    e.preventDefault()
    setFormError('')
    setSavedAppointment(null)

    if (!customerName.trim() || !customerPhone.trim() || !serviceId || !appointmentDate || !appointmentTime) {
      setFormError('لطفاً همه فیلدها را کامل کنید.')
      return
    }

    setSubmitting(true)

    fetch('/api/appointments', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
      },
      body: JSON.stringify({
        customer_name: customerName,
        customer_phone: customerPhone,
        service_id: Number(serviceId),
        appointment_date: appointmentDate,
        appointment_time: appointmentTime,
      }),
    })
      .then(async (response) => {
        const data = await response.json()
        if (!response.ok) {
          throw new Error(data.message || 'خطا در ثبت نوبت')
        }
        return data
      })
      .then((data) => {
        setSavedAppointment(data.appointment)
        setCustomerName('')
        setCustomerPhone('')
      })
      .catch((err) => {
        setFormError(err.message || 'خطا در ثبت نوبت')
      })
      .finally(() => {
        setSubmitting(false)
      })
  }

  return (
    <div className="page" dir="rtl">
      <header className="header">
        <div className="logo" onClick={() => setPage('home')} style={{ cursor: 'pointer' }}>
          پیرایش عارف
        </div>
      </header>

      <main className="main">
        {page === 'home' ? (
          <>
            <h1 className="title">خدمات ما</h1>

            {error && <p className="error">{error}</p>}

            <div className="cards">
              {services.map((service) => (
                <div className="card" key={service.id}>
                  <h2 className="card-name">{service.name}</h2>
                  <div className="card-row">
                    <span>قیمت</span>
                    <strong>{formatPrice(service.price)} تومان</strong>
                  </div>
                  <div className="card-row">
                    <span>مدت زمان</span>
                    <strong>{Number(service.duration).toLocaleString('fa-IR')} دقیقه</strong>
                  </div>
                </div>
              ))}
            </div>

            <div className="booking-action">
              <button type="button" className="primary-btn" onClick={handleOpenBooking}>
                نوبت گیری
              </button>
            </div>
          </>
        ) : (
          <div className="booking-container">
            <h1 className="title">رزرو نوبت</h1>

            <form className="booking-form" onSubmit={handleSubmit}>
              <div className="form-group">
                <label htmlFor="customerName">نام و نام خانوادگی</label>
                <input
                  id="customerName"
                  type="text"
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
                <label htmlFor="appointmentDate">انتخاب روز</label>
                <input
                  id="appointmentDate"
                  type="date"
                  value={appointmentDate}
                  onChange={(e) => setAppointmentDate(e.target.value)}
                  required
                />
              </div>

              <div className="form-group">
                <label htmlFor="appointmentTime">انتخاب ساعت (۱۰ تا ۲۰)</label>
                <select
                  id="appointmentTime"
                  value={appointmentTime}
                  onChange={(e) => setAppointmentTime(e.target.value)}
                  required
                >
                  {TIME_SLOTS.map((slot) => (
                    <option key={slot} value={slot}>
                      {slot}
                    </option>
                  ))}
                </select>
              </div>

              {formError && <p className="error">{formError}</p>}

              <div className="form-actions">
                <button type="submit" className="primary-btn" disabled={submitting}>
                  {submitting ? 'در حال ثبت...' : 'ثبت'}
                </button>
                <button
                  type="button"
                  className="secondary-btn"
                  onClick={() => setPage('home')}
                >
                  بازگشت به صفحه اصلی
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
                  <strong>{savedAppointment.customer_phone}</strong>
                </div>
                <div className="summary-row">
                  <span>خدمت انتخاب‌شده:</span>
                  <strong>{savedAppointment.service?.name}</strong>
                </div>
                <div className="summary-row">
                  <span>روز نوبت:</span>
                  <strong>{savedAppointment.appointment_date}</strong>
                </div>
                <div className="summary-row">
                  <span>ساعت نوبت:</span>
                  <strong>{savedAppointment.appointment_time}</strong>
                </div>
              </div>
            )}
          </div>
        )}
      </main>
    </div>
  )
}

export default App

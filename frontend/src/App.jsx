import { useEffect, useRef, useState } from 'react'

// نمایش قیمت با جداکننده هزارگان و اعداد فارسی
function formatPrice(price) {
  return Number(price).toLocaleString('fa-IR')
}

// تبدیل اعداد انگلیسی به فارسی
function toPersianDigits(val) {
  return String(val).replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d])
}

const JALALI_MONTHS = [
  'فروردین',
  'اردیبهشت',
  'خرداد',
  'تیر',
  'مرداد',
  'شهریور',
  'مهر',
  'آبان',
  'آذر',
  'دی',
  'بهمن',
  'اسفند',
]

const WEEK_DAYS = ['ش', 'ی', 'د', 'س', 'چ', 'پ', 'ج']

// تبدیل تاریخ میلادی به شمسی
function gregorianToJalali(gy, gm, gd) {
  const g_d_m = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334]
  const gy2 = gm > 2 ? gy + 1 : gy
  let days =
    355666 +
    365 * gy +
    Math.floor((gy2 + 3) / 4) -
    Math.floor((gy2 + 99) / 100) +
    Math.floor((gy2 + 399) / 400) +
    gd +
    g_d_m[gm - 1]
  let jy = -1595 + 33 * Math.floor(days / 12053)
  days %= 12053
  jy += 4 * Math.floor(days / 1461)
  days %= 1461
  if (days > 365) {
    jy += Math.floor((days - 1) / 365)
    days = (days - 1) % 365
  }
  const jm = days < 186 ? 1 + Math.floor(days / 31) : 7 + Math.floor((days - 186) / 30)
  const jd = 1 + (days < 186 ? days % 31 : (days - 186) % 30)
  return [jy, jm, jd]
}

// تبدیل تاریخ شمسی به میلادی
function jalaliToGregorian(jy, jm, jd) {
  const jy1 = jy + 1595
  let days =
    -355668 +
    365 * jy1 +
    Math.floor(jy1 / 33) * 8 +
    Math.floor(((jy1 % 33) + 3) / 4) +
    jd +
    (jm < 7 ? (jm - 1) * 31 : (jm - 7) * 30 + 186)
  let gy = 400 * Math.floor(days / 146097)
  days %= 146097
  if (days > 36524) {
    gy += 100 * Math.floor(--days / 36524)
    days %= 36524
    if (days >= 365) days++
  }
  gy += 4 * Math.floor(days / 1461)
  days %= 1461
  if (days > 365) {
    gy += Math.floor((days - 1) / 365)
    days = (days - 1) % 365
  }
  let gd = days + 1
  const sal_a = [
    0,
    31,
    (gy % 4 === 0 && gy % 100 !== 0) || gy % 400 === 0 ? 29 : 28,
    31,
    30,
    31,
    30,
    31,
    31,
    30,
    31,
    30,
    31,
  ]
  let gm
  for (gm = 0; gm < 13 && gd > sal_a[gm]; gm++) {
    gd -= sal_a[gm]
  }
  return [gy, gm, gd]
}

// تعداد روزهای یک ماه شمسی
function getJalaliMonthLength(jy, jm) {
  if (jm <= 6) return 31
  if (jm <= 11) return 30
  // بررسی کبیسه بودن اسفند
  const [gy1, gm1, gd1] = jalaliToGregorian(jy, 12, 30)
  const [jyCheck] = gregorianToJalali(gy1, gm1, gd1)
  return jyCheck === jy ? 30 : 29
}

// تبدیل رشته YYYY-MM-DD میلادی به نمایش شمسی (مثلاً ۱۴۰۵/۰۸/۲۱)
function formatGregorianToJalaliDisplay(dateStr) {
  if (!dateStr) return ''
  const parts = String(dateStr).split('T')[0].split('-').map(Number)
  if (parts.length !== 3) return dateStr
  const [jy, jm, jd] = gregorianToJalali(parts[0], parts[1], parts[2])
  const y = toPersianDigits(jy)
  const m = toPersianDigits(String(jm).padStart(2, '0'))
  const d = toPersianDigits(String(jd).padStart(2, '0'))
  return `${y}/${m}/${d}`
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

function JalaliDatePicker({ value, onChange }) {
  const now = new Date()
  const [todayJy, todayJm, todayJd] = gregorianToJalali(
    now.getFullYear(),
    now.getMonth() + 1,
    now.getDate()
  )

  const [isOpen, setIsOpen] = useState(false)
  const [viewYear, setViewYear] = useState(todayJy)
  const [viewMonth, setViewMonth] = useState(todayJm)
  const containerRef = useRef(null)

  useEffect(() => {
    function handleClickOutside(e) {
      if (containerRef.current && !containerRef.current.contains(e.target)) {
        setIsOpen(false)
      }
    }
    document.addEventListener('mousedown', handleClickOutside)
    return () => document.removeEventListener('mousedown', handleClickOutside)
  }, [])

  // پیدا کردن روز هفته برای اول ماه شمسی (۰ = شنبه تا ۶ = جمعه)
  const [firstGy, firstGm, firstGd] = jalaliToGregorian(viewYear, viewMonth, 1)
  const firstDayOfWeek = (new Date(firstGy, firstGm - 1, firstGd).getDay() + 1) % 7
  const daysInMonth = getJalaliMonthLength(viewYear, viewMonth)

  let selectedJy = null
  let selectedJm = null
  let selectedJd = null
  if (value) {
    const parts = value.split('-').map(Number)
    if (parts.length === 3) {
      ;[selectedJy, selectedJm, selectedJd] = gregorianToJalali(parts[0], parts[1], parts[2])
    }
  }

  function handlePrevMonth() {
    if (viewMonth === 1) {
      setViewMonth(12)
      setViewYear((y) => y - 1)
    } else {
      setViewMonth((m) => m - 1)
    }
  }

  function handleNextMonth() {
    if (viewMonth === 12) {
      setViewMonth(1)
      setViewYear((y) => y + 1)
    } else {
      setViewMonth((m) => m + 1)
    }
  }

  function handleSelectDay(day) {
    const [gy, gm, gd] = jalaliToGregorian(viewYear, viewMonth, day)
    const isoDate = `${gy}-${String(gm).padStart(2, '0')}-${String(gd).padStart(2, '0')}`
    onChange(isoDate)
    setIsOpen(false)
  }

  function handleSelectToday() {
    setViewYear(todayJy)
    setViewMonth(todayJm)
    const [gy, gm, gd] = jalaliToGregorian(todayJy, todayJm, todayJd)
    const isoDate = `${gy}-${String(gm).padStart(2, '0')}-${String(gd).padStart(2, '0')}`
    onChange(isoDate)
    setIsOpen(false)
  }

  const cells = []
  for (let i = 0; i < firstDayOfWeek; i++) {
    cells.push(null)
  }
  for (let d = 1; d <= daysInMonth; d++) {
    cells.push(d)
  }

  return (
    <div className="jalali-datepicker" ref={containerRef}>
      <input
        id="appointmentDate"
        type="text"
        readOnly
        value={value ? formatGregorianToJalaliDisplay(value) : ''}
        placeholder="انتخاب تاریخ شمسی (مثلاً ۱۴۰۵/۰۸/۲۱)"
        onClick={() => setIsOpen((o) => !o)}
        className="jalali-input"
        required
      />
      {isOpen && (
        <div className="jalali-popover">
          <div className="jalali-header">
            <button type="button" className="jalali-nav-btn" onClick={handlePrevMonth}>
              ماه قبل
            </button>
            <span className="jalali-month-title">
              {JALALI_MONTHS[viewMonth - 1]} {toPersianDigits(viewYear)}
            </span>
            <button type="button" className="jalali-nav-btn" onClick={handleNextMonth}>
              ماه بعد
            </button>
          </div>

          <div className="jalali-weekdays">
            {WEEK_DAYS.map((w) => (
              <span key={w}>{w}</span>
            ))}
          </div>

          <div className="jalali-days-grid">
            {cells.map((day, idx) => {
              if (!day) {
                return <span key={`empty-${idx}`} className="jalali-day-empty" />
              }
              const isSelected =
                selectedJy === viewYear && selectedJm === viewMonth && selectedJd === day
              const isToday = todayJy === viewYear && todayJm === viewMonth && todayJd === day

              return (
                <button
                  key={day}
                  type="button"
                  className={`jalali-day-btn ${isSelected ? 'selected' : ''} ${
                    isToday ? 'today' : ''
                  }`}
                  onClick={() => handleSelectDay(day)}
                >
                  {toPersianDigits(day)}
                </button>
              )
            })}
          </div>

          <div className="jalali-footer">
            <button type="button" className="jalali-today-btn" onClick={handleSelectToday}>
              برو به امروز ({toPersianDigits(todayJd)} {JALALI_MONTHS[todayJm - 1]}{' '}
              {toPersianDigits(todayJy)})
            </button>
          </div>
        </div>
      )}
    </div>
  )
}

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
                <label htmlFor="appointmentDate">انتخاب روز (شمسی)</label>
                <JalaliDatePicker value={appointmentDate} onChange={setAppointmentDate} />
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
                  <strong>{formatGregorianToJalaliDisplay(savedAppointment.appointment_date)}</strong>
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

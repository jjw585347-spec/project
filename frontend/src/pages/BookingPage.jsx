import { useEffect, useMemo, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import JalaliDatePicker from '../components/JalaliDatePicker'
import TimeSlotPicker from '../components/TimeSlotPicker'
import { apiRequest } from '../utils/api'
import { formatGregorianToJalaliDisplay, getTodayIsoDate } from '../utils/jalali'
import {
  formatPrice,
  minutesToTime,
  TIME_SLOTS,
  timeToMinutes,
  toPersianDigits,
} from '../utils/persian'

const BOOKING_STATE_KEY = 'barbershop_booking_page_state'

function loadStoredBookingState() {
  try {
    const raw = sessionStorage.getItem(BOOKING_STATE_KEY)
    return raw ? JSON.parse(raw) : null
  } catch {
    return null
  }
}

function intervalsOverlap(startA, endA, startB, endB) {
  return startA < endB && endA > startB
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

  // پشتیبانی از انتخاب یک یا چند خدمت (گام اول طبق R8)
  const [selectedServiceIds, setSelectedServiceIds] = useState(() => {
    if (Array.isArray(stored?.selectedServiceIds) && stored.selectedServiceIds.length > 0) {
      return stored.selectedServiceIds.map(String)
    }
    if (stored?.serviceId) {
      return [String(stored.serviceId)]
    }
    return services[0]?.id ? [String(services[0].id)] : []
  })

  const [appointmentDate, setAppointmentDate] = useState(stored?.appointmentDate || '')
  const [appointmentTime, setAppointmentTime] = useState(stored?.appointmentTime || '')

  // وضعیت برنامه کاری، مرخصی‌ها و بازه‌های رزروشده در روز انتخابی
  const [daySchedule, setDaySchedule] = useState({
    startTime: '10:00',
    endTime: '20:00',
    workingSlots: TIME_SLOTS,
    bookedIntervals: [],
    hourlyLeaves: [],
    isClosed: false,
    closeReason: '',
  })

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

  // هماهنگ‌سازی لیست خدمات انتخاب‌شده با خدمات فعال موجود
  useEffect(() => {
    if (services.length === 0) {
      if (selectedServiceIds.length > 0) {
        setSelectedServiceIds([])
      }
      return
    }
    const activeIdSet = new Set(services.map((s) => String(s.id)))
    const validSelected = selectedServiceIds.filter((id) => activeIdSet.has(String(id)))
    if (validSelected.length !== selectedServiceIds.length) {
      setSelectedServiceIds(
        validSelected.length > 0 ? validSelected : [String(services[0].id)]
      )
    } else if (validSelected.length === 0 && selectedServiceIds.length === 0) {
      // اگر کاربر خودش همه را برنداشته و در بارگذاری اولیه لیست خدمات تازه رسیده است
      if (!stored?.selectedServiceIds) {
        setSelectedServiceIds([String(services[0].id)])
      }
    }
  }, [services])

  // محاسبه خدمات انتخاب‌شده، مجموع مدت (دقیقه) و مجموع قیمت (تومان)
  const selectedServices = useMemo(() => {
    const idSet = new Set(selectedServiceIds.map(String))
    return services.filter((s) => idSet.has(String(s.id)))
  }, [services, selectedServiceIds])

  const totalDuration = useMemo(() => {
    return selectedServices.reduce((sum, srv) => sum + (Number(srv.duration) || 0), 0)
  }, [selectedServices])

  const totalPrice = useMemo(() => {
    return selectedServices.reduce((sum, srv) => sum + (Number(srv.price) || 0), 0)
  }, [selectedServices])

  // ذخیره وضعیت فرم و کادر سبز خلاصه نوبت در sessionStorage تا با رفرش صفحه نپرد
  useEffect(() => {
    try {
      sessionStorage.setItem(
        BOOKING_STATE_KEY,
        JSON.stringify({
          customerName,
          customerPhone,
          selectedServiceIds,
          serviceId: selectedServiceIds[0] || '',
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
    selectedServiceIds,
    appointmentDate,
    appointmentTime,
    savedAppointment,
  ])

  // دریافت برنامه کاری، تعطیلی، مرخصی‌ها و بازه‌های رزروشده روز انتخابی از بک‌اند
  useEffect(() => {
    if (!appointmentDate) {
      setDaySchedule({
        startTime: '10:00',
        endTime: '20:00',
        workingSlots: TIME_SLOTS,
        bookedIntervals: [],
        hourlyLeaves: [],
        isClosed: false,
        closeReason: '',
      })
      return
    }

    const durationParam = totalDuration > 0 ? `&duration=${encodeURIComponent(totalDuration)}` : ''
    apiRequest(
      `/api/appointments/booked-slots?date=${encodeURIComponent(appointmentDate)}${durationParam}`
    )
      .then((data) => {
        const dayWorkingSlots =
          Array.isArray(data.working_slots) && data.working_slots.length > 0
            ? data.working_slots
            : TIME_SLOTS
        const intervals = Array.isArray(data.booked_intervals)
          ? data.booked_intervals
          : Array.isArray(data.booked_slots)
            ? data.booked_slots.map((slot) => ({
                start_time: slot,
                end_time: minutesToTime(timeToMinutes(slot) + 30),
                duration: 30,
              }))
            : []
        const leaves = Array.isArray(data.hourly_leaves)
          ? data.hourly_leaves
          : Array.isArray(data.leave_slots)
            ? data.leave_slots.map((slot) => ({
                start_time: slot,
                end_time: minutesToTime(timeToMinutes(slot) + 30),
              }))
            : []

        setDaySchedule({
          startTime: data.start_time || '10:00',
          endTime: data.end_time || '20:00',
          workingSlots: dayWorkingSlots,
          bookedIntervals: intervals,
          hourlyLeaves: leaves,
          isClosed: Boolean(data.is_closed),
          closeReason: data.close_reason || '',
        })
      })
      .catch(() => {
        setDaySchedule({
          startTime: '10:00',
          endTime: '20:00',
          workingSlots: TIME_SLOTS,
          bookedIntervals: [],
          hourlyLeaves: [],
          isClosed: false,
          closeReason: '',
        })
      })
  }, [appointmentDate, totalDuration])

  // محاسبه دقیق فقط زمان‌هایی که مجموع خدمات انتخاب‌شده کامل در آن جا می‌شوند (R3, R5, R6, R7, R8)
  const fittingSlots = useMemo(() => {
    if (
      !appointmentDate ||
      daySchedule.isClosed ||
      selectedServices.length === 0 ||
      totalDuration <= 0
    ) {
      return []
    }

    const workStartMin = timeToMinutes(daySchedule.startTime)
    const workEndMin = timeToMinutes(daySchedule.endTime)
    const todayIso = getTodayIsoDate()
    const isToday = appointmentDate === todayIso
    const now = new Date()
    const nowMinutes = now.getHours() * 60 + now.getMinutes()

    return daySchedule.workingSlots.filter((slot) => {
      const slotStartMin = timeToMinutes(slot)
      const slotEndMin = slotStartMin + totalDuration

      // ۱. داخل ساعت کاری روز (R1, R6, R7): کل خدمت باید قبل از پایان ساعت کاری تمام شود
      if (slotStartMin < workStartMin || slotEndMin > workEndMin) {
        return false
      }

      // ۲. در روز جاری ساعت شروع نگذشته باشد (R3)
      if (isToday && slotStartMin <= nowMinutes) {
        return false
      }

      // ۳. بیرون از بازه‌های مرخصی ساعتی (R7)
      for (const leave of daySchedule.hourlyLeaves) {
        const leaveStartMin = timeToMinutes(leave.start_time)
        const leaveEndMin = timeToMinutes(leave.end_time)
        if (intervalsOverlap(slotStartMin, slotEndMin, leaveStartMin, leaveEndMin)) {
          return false
        }
      }

      // ۴. بدون هم‌پوشانی با نوبت‌های فعال دیگر (R5)
      for (const booked of daySchedule.bookedIntervals) {
        const bookedStartMin = timeToMinutes(booked.start_time)
        const bookedEndMin = booked.end_time
          ? timeToMinutes(booked.end_time)
          : bookedStartMin + (Number(booked.duration) || 30)
        if (intervalsOverlap(slotStartMin, slotEndMin, bookedStartMin, bookedEndMin)) {
          return false
        }
      }

      return true
    })
  }, [appointmentDate, daySchedule, selectedServices.length, totalDuration])

  // اگر ساعت انتخابی فعلی دیگر در لیست ساعت‌های مجاز جا نشود، اولین ساعت مجاز انتخاب شود
  useEffect(() => {
    if (!appointmentDate || daySchedule.isClosed || fittingSlots.length === 0) {
      if (appointmentTime !== '' && fittingSlots.length === 0 && appointmentDate) {
        setAppointmentTime('')
      }
      return
    }
    if (!fittingSlots.includes(appointmentTime)) {
      setAppointmentTime(fittingSlots[0])
    }
  }, [fittingSlots, appointmentDate, daySchedule.isClosed, appointmentTime])

  function handleToggleService(idStr) {
    clearFieldError('service_id')
    setSelectedServiceIds((prev) => {
      const exists = prev.includes(idStr)
      if (exists) {
        return prev.filter((item) => item !== idStr)
      }
      return [...prev, idStr]
    })
  }

  async function handleSubmit(e) {
    e.preventDefault()
    setFormError('')
    setFieldErrors({})
    setSavedAppointment(null)

    const numericServiceIds = selectedServiceIds
      .map((id) => Number(id))
      .filter((id) => Number.isInteger(id) && id > 0)

    setSubmitting(true)
    try {
      const data = await apiRequest('/api/appointments', {
        method: 'POST',
        body: JSON.stringify({
          customer_id: customer?.id || null,
          customer_name: customerName.trim(),
          customer_phone: customerPhone.trim(),
          service_id: numericServiceIds[0] || null,
          service_ids: numericServiceIds,
          appointment_date: appointmentDate,
          appointment_time: appointmentTime,
        }),
      })

      const created = data.appointment
      setSavedAppointment(created)

      // بلافاصله بازه اشغال‌شده جدید را به لیست بازه‌های رزروشده روز اضافه می‌کنیم تا ساعت‌های متداخل حذف شوند
      const bookedDur = Number(created?.total_duration) || totalDuration || 30
      const startMin = timeToMinutes(appointmentTime)
      setDaySchedule((prev) => ({
        ...prev,
        bookedIntervals: [
          ...prev.bookedIntervals,
          {
            start_time: appointmentTime,
            end_time: minutesToTime(startMin + bookedDur),
            duration: bookedDur,
          },
        ],
      }))
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

  const savedServiceNames = useMemo(() => {
    if (!savedAppointment) return ''
    if (Array.isArray(savedAppointment.services) && savedAppointment.services.length > 0) {
      return savedAppointment.services.map((s) => s.name).join('، ')
    }
    return savedAppointment.service?.name || ''
  }, [savedAppointment])

  return (
    <div className="booking-container">
      <h1 className="title">رزرو نوبت</h1>

      <form className="booking-form" onSubmit={handleSubmit} noValidate>
        {/* گام ۱: انتخاب یک یا چند خدمت (R8) */}
        <div className="form-group">
          <label>۱. انتخاب یک یا چند خدمت</label>
          <div
            className={`service-multi-list ${fieldErrors.service_id ? 'input-invalid' : ''}`}
            role="group"
            aria-label="انتخاب خدمات آرایشگاه"
          >
            {services.map((service) => {
              const idStr = String(service.id)
              const isChecked = selectedServiceIds.includes(idStr)
              return (
                <button
                  key={service.id}
                  type="button"
                  aria-pressed={isChecked}
                  onClick={() => handleToggleService(idStr)}
                  className={`time-slot-card service-slot-card ${isChecked ? 'selected' : ''}`}
                >
                  <span className="time-slot-text">{service.name}</span>
                  <span className="time-slot-end-hint">
                    {formatPrice(service.price)} تومان —{' '}
                    {toPersianDigits(service.duration)} دقیقه
                  </span>
                </button>
              )
            })}
          </div>

          {selectedServices.length > 0 && (
            <div className="selected-services-summary">
              <span>
                تعداد خدمات: <strong>{toPersianDigits(selectedServices.length)}</strong>
              </span>
              <span>
                مجموع زمان: <strong>{toPersianDigits(totalDuration)} دقیقه</strong>
              </span>
              <span>
                مجموع هزینه: <strong>{formatPrice(totalPrice)} تومان</strong>
              </span>
            </div>
          )}

          {fieldErrors.service_id && (
            <p className="field-error" role="alert">
              {fieldErrors.service_id}
            </p>
          )}
        </div>

        {/* گام ۲: انتخاب روز (شمسی) */}
        <div className="form-group">
          <label htmlFor="appointmentDate">۲. انتخاب روز (شمسی)</label>
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

        {/* گام ۳: نمایش فقط ساعت‌هایی که خدمات انتخاب‌شده کامل در آن جا می‌شوند */}
        <div className="form-group">
          <label>۳. انتخاب ساعت (فقط زمان‌های قابل رزرو برای خدمات انتخابی)</label>
          <TimeSlotPicker
            value={appointmentTime}
            onChange={(val) => {
              setAppointmentTime(val)
              clearFieldError('appointment_time')
            }}
            fittingSlots={fittingSlots}
            totalDuration={totalDuration}
            hasSelectedDate={Boolean(appointmentDate)}
            hasSelectedServices={selectedServices.length > 0}
            isDayClosed={daySchedule.isClosed}
            closeReason={daySchedule.closeReason}
          />
          {fieldErrors.appointment_time && (
            <p className="field-error" role="alert">
              {fieldErrors.appointment_time}
            </p>
          )}
        </div>

        {/* اطلاعات تماس مشتری */}
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

        {formError && <p className="error">{formError}</p>}

        <div className="form-actions">
          <button
            type="submit"
            className="primary-btn"
            disabled={submitting || daySchedule.isClosed}
          >
            {submitting
              ? 'در حال ثبت...'
              : daySchedule.isClosed
                ? 'این روز تعطیل است'
                : 'ثبت نوبت'}
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
            <span>خدمات انتخاب‌شده:</span>
            <strong>{savedServiceNames}</strong>
          </div>
          <div className="summary-row">
            <span>مجموع مدت زمان:</span>
            <strong>
              {toPersianDigits(savedAppointment.total_duration || totalDuration)} دقیقه
            </strong>
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

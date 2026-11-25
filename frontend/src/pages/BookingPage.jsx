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
const TRACKING_PREFILL_KEY = 'barbershop_tracking_prefill'

const STEPS = [
  { id: 1, title: 'انتخاب خدمت‌ها' },
  { id: 2, title: 'انتخاب روز و زمان' },
  { id: 3, title: 'نام و موبایل' },
  { id: 4, title: 'تأیید' },
]

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

function normalizePhoneDigits(raw) {
  if (!raw) return ''
  return String(raw)
    .replace(/[۰-۹]/g, (d) => String('۰۱۲۳۴۵۶۷۸۹'.indexOf(d)))
    .replace(/[٠-٩]/g, (d) => String('٠١٢٣٤٥٦٧٨٩'.indexOf(d)))
    .trim()
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

  const [currentStep, setCurrentStep] = useState(() => {
    const s = Number(stored?.currentStep)
    return s >= 1 && s <= 4 ? s : 1
  })

  const [customerName, setCustomerName] = useState(
    stored?.customerName !== undefined ? stored.customerName : customer?.name || ''
  )
  const [customerPhone, setCustomerPhone] = useState(
    stored?.customerPhone !== undefined ? stored.customerPhone : customer?.phone || ''
  )

  // گام ۱: انتخاب یک یا چند خدمت (R8)
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

  const selectedServiceNames = useMemo(() => {
    if (selectedServices.length === 0) return 'هنوز خدمتی انتخاب نشده است'
    return selectedServices.map((s) => s.name).join('، ')
  }, [selectedServices])

  const totalDuration = useMemo(() => {
    return selectedServices.reduce((sum, srv) => sum + (Number(srv.duration) || 0), 0)
  }, [selectedServices])

  const totalPrice = useMemo(() => {
    return selectedServices.reduce((sum, srv) => sum + (Number(srv.price) || 0), 0)
  }, [selectedServices])

  // ذخیره وضعیت فرم و کادر خلاصه نوبت در sessionStorage
  useEffect(() => {
    try {
      sessionStorage.setItem(
        BOOKING_STATE_KEY,
        JSON.stringify({
          currentStep,
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
    currentStep,
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

      if (slotStartMin < workStartMin || slotEndMin > workEndMin) {
        return false
      }

      if (isToday && slotStartMin <= nowMinutes) {
        return false
      }

      for (const leave of daySchedule.hourlyLeaves) {
        const leaveStartMin = timeToMinutes(leave.start_time)
        const leaveEndMin = timeToMinutes(leave.end_time)
        if (intervalsOverlap(slotStartMin, slotEndMin, leaveStartMin, leaveEndMin)) {
          return false
        }
      }

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

  function validateStep1() {
    if (selectedServices.length === 0) {
      setFieldErrors((prev) => ({
        ...prev,
        service_id: 'لطفاً حداقل یکی از خدمات آرایشگاه را انتخاب کنید.',
      }))
      return false
    }
    clearFieldError('service_id')
    return true
  }

  function validateStep2() {
    const nextErrors = {}
    if (!appointmentDate) {
      nextErrors.appointment_date = 'لطفاً یک تاریخ معتبر برای نوبت انتخاب کنید.'
    } else if (daySchedule.isClosed) {
      nextErrors.appointment_date =
        daySchedule.closeReason || 'آرایشگاه در این روز تعطیل است. لطفاً روز دیگری را انتخاب کنید.'
    }

    if (!nextErrors.appointment_date) {
      if (!appointmentTime || !fittingSlots.includes(appointmentTime)) {
        nextErrors.appointment_time = 'لطفاً یکی از ساعت‌های قابل رزرو را انتخاب کنید.'
      }
    }

    if (Object.keys(nextErrors).length > 0) {
      setFieldErrors((prev) => ({ ...prev, ...nextErrors }))
      return false
    }
    clearFieldError('appointment_date')
    clearFieldError('appointment_time')
    return true
  }

  function validateStep3() {
    const nextErrors = {}
    const trimmedName = customerName.trim()
    const normalizedPhone = normalizePhoneDigits(customerPhone)

    if (!trimmedName) {
      nextErrors.customer_name = 'لطفاً نام و نام خانوادگی را وارد کنید.'
    }
    if (!normalizedPhone) {
      nextErrors.customer_phone = 'لطفاً شماره موبایل را وارد کنید.'
    } else if (!/^09\d{9}$/.test(normalizedPhone)) {
      nextErrors.customer_phone =
        'شماره موبایل واردشده معتبر نیست. لطفاً یک شماره موبایل ۱۱ رقمی که با ۰۹ شروع می‌شود (مثلاً 09123456789) وارد کنید.'
    }

    if (Object.keys(nextErrors).length > 0) {
      setFieldErrors((prev) => ({ ...prev, ...nextErrors }))
      return false
    }
    clearFieldError('customer_name')
    clearFieldError('customer_phone')
    return true
  }

  function handleNextStep() {
    setFormError('')
    if (currentStep === 1) {
      if (validateStep1()) setCurrentStep(2)
    } else if (currentStep === 2) {
      if (validateStep2()) setCurrentStep(3)
    } else if (currentStep === 3) {
      if (validateStep3()) setCurrentStep(4)
    }
  }

  function handlePrevStep() {
    setFormError('')
    if (currentStep > 1) {
      setCurrentStep((prev) => prev - 1)
    }
  }

  function handleJumpToStep(targetStep) {
    if (savedAppointment) return
    if (targetStep < currentStep) {
      setFormError('')
      setCurrentStep(targetStep)
      return
    }
    if (targetStep === 2 && validateStep1()) {
      setCurrentStep(2)
    } else if (targetStep === 3 && validateStep1() && validateStep2()) {
      setCurrentStep(3)
    } else if (targetStep === 4 && validateStep1() && validateStep2() && validateStep3()) {
      setCurrentStep(4)
    }
  }

  async function handleSubmit(e) {
    e.preventDefault()
    setFormError('')
    setFieldErrors({})

    if (!validateStep1()) {
      setCurrentStep(1)
      return
    }
    if (!validateStep2()) {
      setCurrentStep(2)
      return
    }
    if (!validateStep3()) {
      setCurrentStep(3)
      return
    }

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
        if (err.errors.service_id) {
          setCurrentStep(1)
        } else if (err.errors.appointment_date || err.errors.appointment_time) {
          setCurrentStep(2)
        } else if (err.errors.customer_name || err.errors.customer_phone) {
          setCurrentStep(3)
        }
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

  function handleStartNewBooking() {
    setSavedAppointment(null)
    setAppointmentTime('')
    setFormError('')
    setFieldErrors({})
    setCurrentStep(1)
  }

  function handleGoToTrackingPage() {
    if (savedAppointment) {
      try {
        sessionStorage.setItem(
          TRACKING_PREFILL_KEY,
          JSON.stringify({
            customerPhone: savedAppointment.customer_phone || customerPhone,
            trackingCode: savedAppointment.tracking_code || '',
          })
        )
      } catch {
        //
      }
    }
    navigate('/track')
  }

  const savedServiceNames = useMemo(() => {
    if (!savedAppointment) return ''
    if (Array.isArray(savedAppointment.services) && savedAppointment.services.length > 0) {
      return savedAppointment.services.map((s) => s.name).join('، ')
    }
    return savedAppointment.service?.name || selectedServiceNames
  }, [savedAppointment, selectedServiceNames])

  const savedTotalPrice = useMemo(() => {
    if (!savedAppointment) return totalPrice
    if (savedAppointment.total_price !== undefined && savedAppointment.total_price !== null) {
      return Number(savedAppointment.total_price)
    }
    if (Array.isArray(savedAppointment.services) && savedAppointment.services.length > 0) {
      return savedAppointment.services.reduce((sum, s) => sum + (Number(s.price) || 0), 0)
    }
    return Number(savedAppointment.service?.price) || totalPrice
  }, [savedAppointment, totalPrice])

  const savedTotalDuration = useMemo(() => {
    if (!savedAppointment) return totalDuration
    return Number(savedAppointment.total_duration) || totalDuration
  }, [savedAppointment, totalDuration])

  const selectedEndTime = useMemo(() => {
    if (!appointmentTime || totalDuration <= 0) return ''
    return minutesToTime(timeToMinutes(appointmentTime) + totalDuration)
  }, [appointmentTime, totalDuration])

  return (
    <div className="booking-container">
      <h1 className="title">رزرو نوبت</h1>

      {/* نمایش صفحه خلاصه بعد از ثبت نوبت (شامل خدمت، هزینه، روز و ساعت نوبت و کد پیگیری ۶ رقمی) */}
      {savedAppointment ? (
        <div className="success-box" role="region" aria-label="خلاصه نوبت ثبت‌شده">
          <h2 className="success-title">نوبت شما با موفقیت ثبت شد!</h2>

          {savedAppointment.tracking_code && (
            <div className="tracking-code-banner">
              <span>کد پیگیری ۶ رقمی شما:</span>
              <strong className="tracking-code-digits" data-testid="booking-tracking-code">
                {toPersianDigits(savedAppointment.tracking_code)}
              </strong>
              <p className="tracking-code-hint">
                این کد را نگه دارید؛ برای مشاهده یا لغو نوبت (تا ۲ ساعت قبل از نوبت) به این کد و شماره موبایل نیاز دارید.
              </p>
            </div>
          )}

          <div className="summary-row">
            <span>خدمت‌های انتخاب‌شده:</span>
            <strong>{savedServiceNames}</strong>
          </div>
          <div className="summary-row">
            <span>هزینه‌ی کل:</span>
            <strong>{formatPrice(savedTotalPrice)} تومان</strong>
          </div>
          <div className="summary-row">
            <span>مدت کل:</span>
            <strong>{toPersianDigits(savedTotalDuration)} دقیقه</strong>
          </div>
          <div className="summary-row">
            <span>روز نوبت:</span>
            <strong>{formatGregorianToJalaliDisplay(savedAppointment.appointment_date)}</strong>
          </div>
          <div className="summary-row">
            <span>ساعت نوبت:</span>
            <strong>{toPersianDigits(savedAppointment.appointment_time)}</strong>
          </div>
          <div className="summary-row">
            <span>نام مشتری:</span>
            <strong>{savedAppointment.customer_name}</strong>
          </div>
          <div className="summary-row">
            <span>شماره موبایل:</span>
            <strong>{toPersianDigits(savedAppointment.customer_phone)}</strong>
          </div>

          <div className="summary-actions">
            <button type="button" className="primary-btn" onClick={handleGoToTrackingPage}>
              پیگیری یا لغو این نوبت
            </button>
            <button type="button" className="secondary-btn" onClick={handleStartNewBooking}>
              ثبت یک نوبت جدید
            </button>
          </div>
        </div>
      ) : (
        <>
          {/* نوار قدم‌های رزرو: ۱. انتخاب خدمت‌ها -> ۲. انتخاب روز و زمان -> ۳. نام و موبایل -> ۴. تأیید */}
          <div className="booking-stepper" role="navigation" aria-label="مراحل رزرو نوبت">
            {STEPS.map((step) => {
              const isActive = currentStep === step.id
              const isCompleted = currentStep > step.id
              return (
                <button
                  key={step.id}
                  type="button"
                  onClick={() => handleJumpToStep(step.id)}
                  className={`booking-step-pill ${isActive ? 'active' : ''} ${
                    isCompleted ? 'completed' : ''
                  }`}
                  aria-current={isActive ? 'step' : undefined}
                >
                  <span className="booking-step-num">{toPersianDigits(step.id)}</span>
                  <span className="booking-step-label">{step.title}</span>
                </button>
              )
            })}
          </div>

          {/* خلاصه زنده انتخاب‌های مشتری که در تمام قدم‌ها همیشه بالای فرم دیده می‌شود */}
          <div className="live-selection-bar" aria-label="خلاصه انتخاب شما در این لحظه">
            <div className="live-selection-row">
              <span>خدمت‌ها:</span>
              <strong>{selectedServiceNames}</strong>
            </div>
            <div className="live-selection-metrics">
              <span>
                هزینه‌ی کل: <strong>{formatPrice(totalPrice)} تومان</strong>
              </span>
              <span>
                مدت کل: <strong>{toPersianDigits(totalDuration)} دقیقه</strong>
              </span>
            </div>
            {(appointmentDate || appointmentTime) && (
              <div className="live-selection-datetime">
                {appointmentDate && (
                  <span>
                    روز: <strong>{formatGregorianToJalaliDisplay(appointmentDate)}</strong>
                  </span>
                )}
                {appointmentTime && (
                  <span>
                    ساعت:{' '}
                    <strong>
                      {toPersianDigits(appointmentTime)}
                      {selectedEndTime ? ` تا ${toPersianDigits(selectedEndTime)}` : ''}
                    </strong>
                  </span>
                )}
              </div>
            )}
          </div>

          <form className="booking-form" onSubmit={handleSubmit} noValidate>
            {/* قدم ۱: انتخاب خدمت‌ها */}
            {currentStep === 1 && (
              <div className="form-group">
                <label>قدم ۱: انتخاب خدمت‌ها (می‌توانید یک یا چند خدمت انتخاب کنید)</label>
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

                {fieldErrors.service_id && (
                  <p className="field-error" role="alert">
                    {fieldErrors.service_id}
                  </p>
                )}

                <div className="wizard-actions">
                  <button type="button" className="primary-btn" onClick={handleNextStep}>
                    ادامه به انتخاب روز و زمان
                  </button>
                </div>
              </div>
            )}

            {/* قدم ۲: انتخاب روز و زمان */}
            {currentStep === 2 && (
              <>
                <div className="form-group">
                  <label htmlFor="appointmentDate">قدم ۲: انتخاب روز (شمسی)</label>
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
                  <label>انتخاب زمان (فقط ساعت‌هایی که خدمات انتخابی شما کامل جا می‌شوند)</label>
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

                <div className="wizard-actions">
                  <button type="button" className="secondary-btn" onClick={handlePrevStep}>
                    مرحله قبل (انتخاب خدمت‌ها)
                  </button>
                  <button
                    type="button"
                    className="primary-btn"
                    onClick={handleNextStep}
                    disabled={daySchedule.isClosed}
                  >
                    ادامه به نام و موبایل
                  </button>
                </div>
              </>
            )}

            {/* قدم ۳: نام و موبایل */}
            {currentStep === 3 && (
              <>
                <div className="form-group">
                  <label htmlFor="customerName">قدم ۳: نام و نام خانوادگی</label>
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

                <div className="wizard-actions">
                  <button type="button" className="secondary-btn" onClick={handlePrevStep}>
                    مرحله قبل (روز و زمان)
                  </button>
                  <button type="button" className="primary-btn" onClick={handleNextStep}>
                    بررسی و تأیید نهایی
                  </button>
                </div>
              </>
            )}

            {/* قدم ۴: تأیید نهایی */}
            {currentStep === 4 && (
              <div className="confirm-step-box">
                <h2 className="confirm-step-title">قدم ۴: بررسی و تأیید نهایی نوبت</h2>
                <p className="confirm-step-subtitle">
                  لطفاً مشخصات نوبت خود را بررسی کنید و در صورت صحت، دکمه «تأیید و ثبت نهایی نوبت» را بزنید.
                </p>

                <div className="confirm-details-list">
                  <div className="summary-row confirm-detail-row">
                    <span>خدمت‌های انتخاب‌شده:</span>
                    <strong>{selectedServiceNames}</strong>
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
                    <strong>{formatGregorianToJalaliDisplay(appointmentDate)}</strong>
                  </div>
                  <div className="summary-row confirm-detail-row">
                    <span>ساعت نوبت:</span>
                    <strong>
                      {toPersianDigits(appointmentTime)}
                      {selectedEndTime ? ` تا ${toPersianDigits(selectedEndTime)}` : ''}
                    </strong>
                  </div>
                  <div className="summary-row confirm-detail-row">
                    <span>نام و نام خانوادگی:</span>
                    <strong>{customerName.trim()}</strong>
                  </div>
                  <div className="summary-row confirm-detail-row">
                    <span>شماره موبایل:</span>
                    <strong>{toPersianDigits(customerPhone.trim())}</strong>
                  </div>
                </div>

                {formError && <p className="error">{formError}</p>}

                <div className="wizard-actions">
                  <button type="button" className="secondary-btn" onClick={handlePrevStep}>
                    مرحله قبل (ویرایش نام و موبایل)
                  </button>
                  <button
                    type="submit"
                    className="primary-btn"
                    disabled={submitting || daySchedule.isClosed}
                  >
                    {submitting ? 'در حال ثبت...' : 'تأیید و ثبت نهایی نوبت'}
                  </button>
                </div>
              </div>
            )}

            {currentStep !== 4 && formError && <p className="error">{formError}</p>}
          </form>
        </>
      )}

      <div className="page-bottom-nav booking-bottom-links">
        <button type="button" className="secondary-btn" onClick={() => navigate('/track')}>
          پیگیری نوبت با کد پیگیری
        </button>
        <button type="button" className="secondary-btn" onClick={handleBackClick}>
          برگشت به صفحه خدمات
        </button>
      </div>
    </div>
  )
}

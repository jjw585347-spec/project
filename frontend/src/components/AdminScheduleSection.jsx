import { useCallback, useEffect, useState } from 'react'
import { apiRequest } from '../utils/api'
import { formatGregorianToJalaliDisplay, getTodayIsoDate } from '../utils/jalali'
import { toPersianDigits } from '../utils/persian'
import JalaliDatePicker from './JalaliDatePicker'

// تولید تمام بازه‌های ۳۰ دقیقه‌ای شبانه‌روز (از ۰۶:۰۰ تا ۲۳:۳۰) برای انتخاب ساعت شروع و پایان کاری یا مرخصی
const SCHEDULE_TIME_OPTIONS = (() => {
  const list = []
  for (let h = 6; h <= 23; h++) {
    const hh = String(h).padStart(2, '0')
    list.push(`${hh}:00`)
    list.push(`${hh}:30`)
  }
  return list
})()

export default function AdminScheduleSection({ adminToken, onUnauthorized }) {
  const [weeklyHours, setWeeklyHours] = useState([])
  const [holidays, setHolidays] = useState([])
  const [leaves, setLeaves] = useState([])
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState('')
  const [successMessage, setSuccessMessage] = useState('')
  const [warningInfo, setWarningInfo] = useState(null)

  // وضعیت ذخیره ساعت کاری هفتگی
  const [savingWeekly, setSavingWeekly] = useState(false)
  const [weeklyErrors, setWeeklyErrors] = useState({})

  // وضعیت فرم افزودن تاریخ تعطیل خاص
  const [holidayDate, setHolidayDate] = useState('')
  const [holidayReason, setHolidayReason] = useState('')
  const [holidayErrors, setHolidayErrors] = useState({})
  const [savingHoliday, setSavingHoliday] = useState(false)

  // وضعیت فرم افزودن مرخصی چندساعته
  const [leaveDate, setLeaveDate] = useState(() => getTodayIsoDate())
  const [leaveStartTime, setLeaveStartTime] = useState('14:00')
  const [leaveEndTime, setLeaveEndTime] = useState('16:00')
  const [leaveReason, setLeaveReason] = useState('')
  const [leaveErrors, setLeaveErrors] = useState({})
  const [savingLeave, setSavingLeave] = useState(false)

  const [deletingTimeOffId, setDeletingTimeOffId] = useState(null)
  const [confirmDeleteTarget, setConfirmDeleteTarget] = useState(null)

  const loadSchedule = useCallback(async () => {
    if (!adminToken) return
    setLoading(true)
    setError('')
    try {
      const data = await apiRequest('/api/admin/schedule', {
        headers: {
          Authorization: `Bearer ${adminToken}`,
        },
      })
      if (Array.isArray(data.weekly_hours) && data.weekly_hours.length === 7) {
        setWeeklyHours(data.weekly_hours)
      }
      setHolidays(Array.isArray(data.holidays) ? data.holidays : [])
      setLeaves(Array.isArray(data.leaves) ? data.leaves : [])
    } catch (err) {
      if (err.status === 401 && onUnauthorized) {
        onUnauthorized()
        return
      }
      setError(err.message || 'خطا در دریافت تنظیمات ساعت کاری و مرخصی')
    } finally {
      setLoading(false)
    }
  }, [adminToken, onUnauthorized])

  useEffect(() => {
    loadSchedule()
  }, [loadSchedule])

  function handleWeeklyDayChange(dayOfWeek, field, value) {
    setWeeklyHours((prev) =>
      prev.map((item) =>
        item.day_of_week === dayOfWeek ? { ...item, [field]: value } : item
      )
    )
    setWeeklyErrors((prev) => {
      const key = `day_${dayOfWeek}`
      if (!prev[key] && !prev.weekly_hours) return prev
      const next = { ...prev }
      delete next[key]
      delete next.weekly_hours
      return next
    })
  }

  async function handleSaveWeekly(e) {
    e.preventDefault()
    setError('')
    setSuccessMessage('')
    setWarningInfo(null)
    setWeeklyErrors({})
    setSavingWeekly(true)

    try {
      const data = await apiRequest('/api/admin/schedule/weekly', {
        method: 'PUT',
        headers: {
          Authorization: `Bearer ${adminToken}`,
        },
        body: JSON.stringify({
          weekly_hours: weeklyHours,
        }),
      })
      if (Array.isArray(data.weekly_hours) && data.weekly_hours.length === 7) {
        setWeeklyHours(data.weekly_hours)
      }
      setSuccessMessage(data.message || 'ساعت کاری هفتگی با موفقیت ذخیره شد.')
      if (data.warning) {
        setWarningInfo({
          text: data.warning,
          appointments: Array.isArray(data.conflicting_appointments)
            ? data.conflicting_appointments
            : [],
        })
      }
    } catch (err) {
      if (err.status === 401 && onUnauthorized) {
        onUnauthorized()
        return
      }
      if (err.errors && Object.keys(err.errors).length > 0) {
        setWeeklyErrors(err.errors)
      } else {
        setError(err.message || 'خطا در ذخیره ساعت کاری هفتگی')
      }
    } finally {
      setSavingWeekly(false)
    }
  }

  async function handleAddHoliday(e) {
    e.preventDefault()
    setError('')
    setSuccessMessage('')
    setWarningInfo(null)
    setHolidayErrors({})
    setSavingHoliday(true)

    try {
      const data = await apiRequest('/api/admin/schedule/holidays', {
        method: 'POST',
        headers: {
          Authorization: `Bearer ${adminToken}`,
        },
        body: JSON.stringify({
          off_date: holidayDate,
          reason: holidayReason.trim(),
        }),
      })
      setSuccessMessage(data.message || 'تاریخ تعطیل با موفقیت ثبت شد.')
      setHolidayDate('')
      setHolidayReason('')
      if (data.warning) {
        setWarningInfo({
          text: data.warning,
          appointments: Array.isArray(data.conflicting_appointments)
            ? data.conflicting_appointments
            : [],
        })
      }
      await loadSchedule()
    } catch (err) {
      if (err.status === 401 && onUnauthorized) {
        onUnauthorized()
        return
      }
      if (err.errors && Object.keys(err.errors).length > 0) {
        setHolidayErrors(err.errors)
      } else {
        setError(err.message || 'خطا در ثبت تاریخ تعطیل')
      }
    } finally {
      setSavingHoliday(false)
    }
  }

  async function handleAddLeave(e) {
    e.preventDefault()
    setError('')
    setSuccessMessage('')
    setWarningInfo(null)
    setLeaveErrors({})
    setSavingLeave(true)

    try {
      const data = await apiRequest('/api/admin/schedule/leaves', {
        method: 'POST',
        headers: {
          Authorization: `Bearer ${adminToken}`,
        },
        body: JSON.stringify({
          off_date: leaveDate,
          start_time: leaveStartTime,
          end_time: leaveEndTime,
          reason: leaveReason.trim(),
        }),
      })
      setSuccessMessage(data.message || 'مرخصی ساعتی با موفقیت ثبت شد.')
      setLeaveReason('')
      if (data.warning) {
        setWarningInfo({
          text: data.warning,
          appointments: Array.isArray(data.conflicting_appointments)
            ? data.conflicting_appointments
            : [],
        })
      }
      await loadSchedule()
    } catch (err) {
      if (err.status === 401 && onUnauthorized) {
        onUnauthorized()
        return
      }
      if (err.errors && Object.keys(err.errors).length > 0) {
        setLeaveErrors(err.errors)
      } else {
        setError(err.message || 'خطا در ثبت مرخصی ساعتی')
      }
    } finally {
      setSavingLeave(false)
    }
  }

  async function handleDeleteTimeOff(id) {
    setDeletingTimeOffId(id)
    setError('')
    setSuccessMessage('')
    setWarningInfo(null)

    try {
      const data = await apiRequest(`/api/admin/schedule/time-offs/${id}`, {
        method: 'DELETE',
        headers: {
          Authorization: `Bearer ${adminToken}`,
        },
      })
      setSuccessMessage(data.message || 'مورد با موفقیت حذف شد.')
      await loadSchedule()
    } catch (err) {
      if (err.status === 401 && onUnauthorized) {
        onUnauthorized()
        return
      }
      setError(err.message || 'خطا در حذف مورد')
    } finally {
      setDeletingTimeOffId(null)
    }
  }

  async function handleConfirmDeleteTimeOff() {
    if (!confirmDeleteTarget) return
    const targetId = confirmDeleteTarget.id
    setConfirmDeleteTarget(null)
    await handleDeleteTimeOff(targetId)
  }

  return (
    <div className="admin-services-wrapper">
      {confirmDeleteTarget && (
        <div className="confirm-modal-backdrop">
          <div className="confirm-modal-card">
            <h3 className="confirm-modal-title">
              {confirmDeleteTarget.type === 'full_day'
                ? 'تأیید حذف تعطیلی'
                : 'تأیید حذف مرخصی ساعتی'}
            </h3>
            <p className="confirm-modal-text">
              {confirmDeleteTarget.type === 'full_day' ? (
                <>
                  آیا از حذف تعطیلی تاریخ{' '}
                  <strong>{formatGregorianToJalaliDisplay(confirmDeleteTarget.off_date)}</strong>{' '}
                  مطمئن هستید؟
                </>
              ) : (
                <>
                  آیا از حذف مرخصی تاریخ{' '}
                  <strong>{formatGregorianToJalaliDisplay(confirmDeleteTarget.off_date)}</strong> از
                  ساعت <strong>{toPersianDigits(confirmDeleteTarget.start_time)}</strong> تا{' '}
                  <strong>{toPersianDigits(confirmDeleteTarget.end_time)}</strong> مطمئن هستید؟
                </>
              )}
            </p>
            <div className="confirm-modal-actions">
              <button
                type="button"
                className="cancel-btn"
                onClick={handleConfirmDeleteTimeOff}
              >
                بله، حذف شود
              </button>
              <button
                type="button"
                className="secondary-btn"
                onClick={() => setConfirmDeleteTarget(null)}
              >
                انصراف
              </button>
            </div>
          </div>
        </div>
      )}

      {error && <p className="error">{error}</p>}
      {successMessage && <p className="admin-feedback-success">{successMessage}</p>}

      {/* کادر هشدار تداخل با نوبت‌های قبلی (بدون لغو خودکار نوبت‌ها) */}
      {warningInfo && (
        <div className="schedule-warning-box" role="alert">
          <h3 className="schedule-warning-title">توجه: وجود نوبت‌های فعال از قبل ثبت‌شده</h3>
          <p className="schedule-warning-text">{toPersianDigits(warningInfo.text)}</p>
          {warningInfo.appointments.length > 0 && (
            <div className="schedule-conflict-list">
              {warningInfo.appointments.map((appt) => (
                <div key={appt.id} className="schedule-conflict-item">
                  <strong>{appt.customer_name}</strong>
                  <span>موبایل: {toPersianDigits(appt.customer_phone)}</span>
                  <span>تاریخ: {formatGregorianToJalaliDisplay(appt.appointment_date)}</span>
                  <span>ساعت: {toPersianDigits(appt.appointment_time)}</span>
                  {appt.service_name && <span>خدمت: {appt.service_name}</span>}
                </div>
              ))}
            </div>
          )}
        </div>
      )}

      {/* ۱. تنظیم ساعت کاری روزهای هفته (شنبه تا جمعه) */}
      <form className="booking-form admin-service-form" onSubmit={handleSaveWeekly} noValidate>
        <h2 className="admin-section-title">برنامه هفتگی ساعت کاری</h2>

        {loading ? (
          <p className="owner-empty">در حال بارگذاری برنامه هفتگی...</p>
        ) : (
          <div className="weekly-schedule-list">
            {weeklyHours.map((day) => {
              const dayError = weeklyErrors[`day_${day.day_of_week}`]
              return (
                <div
                  key={day.day_of_week}
                  className={`weekly-day-row ${day.is_closed ? 'weekly-day-closed' : ''}`}
                >
                  <div className="weekly-day-name">
                    <strong>{day.day_name}</strong>
                  </div>

                  <div className="weekly-day-toggle">
                    <label className="weekly-checkbox-label">
                      <input
                        type="checkbox"
                        checked={Boolean(day.is_closed)}
                        onChange={(e) =>
                          handleWeeklyDayChange(day.day_of_week, 'is_closed', e.target.checked)
                        }
                      />
                      <span>{day.is_closed ? 'تعطیل' : 'باز (فعال)'}</span>
                    </label>
                  </div>

                  <div className="weekly-day-times">
                    {day.is_closed ? (
                      <span className="cancelled-badge">این روز هفته تعطیل است</span>
                    ) : (
                      <>
                        <div className="weekly-time-field">
                          <label htmlFor={`startTime_${day.day_of_week}`}>از ساعت:</label>
                          <select
                            id={`startTime_${day.day_of_week}`}
                            value={day.start_time}
                            onChange={(e) =>
                              handleWeeklyDayChange(day.day_of_week, 'start_time', e.target.value)
                            }
                          >
                            {SCHEDULE_TIME_OPTIONS.map((t) => (
                              <option key={t} value={t}>
                                {toPersianDigits(t)}
                              </option>
                            ))}
                          </select>
                        </div>

                        <div className="weekly-time-field">
                          <label htmlFor={`endTime_${day.day_of_week}`}>تا ساعت:</label>
                          <select
                            id={`endTime_${day.day_of_week}`}
                            value={day.end_time}
                            onChange={(e) =>
                              handleWeeklyDayChange(day.day_of_week, 'end_time', e.target.value)
                            }
                          >
                            {SCHEDULE_TIME_OPTIONS.map((t) => (
                              <option key={t} value={t}>
                                {toPersianDigits(t)}
                              </option>
                            ))}
                          </select>
                        </div>
                      </>
                    )}
                  </div>

                  {dayError && (
                    <p className="field-error weekly-day-error" role="alert">
                      {dayError}
                    </p>
                  )}
                </div>
              )
            })}
          </div>
        )}

        {weeklyErrors.weekly_hours && (
          <p className="field-error" role="alert">
            {weeklyErrors.weekly_hours}
          </p>
        )}

        <div className="admin-form-actions">
          <button type="submit" className="primary-btn" disabled={savingWeekly}>
            {savingWeekly ? 'در حال ذخیره...' : 'ذخیره ساعت کاری هفتگی'}
          </button>
        </div>
      </form>

      {/* ۲. تعطیل کردن یک تاریخ خاص */}
      <form className="booking-form admin-service-form" onSubmit={handleAddHoliday} noValidate>
        <h2 className="admin-section-title">تعطیل کردن یک تاریخ خاص</h2>

        <div className="admin-schedule-two-cols">
          <div className="form-group">
            <label htmlFor="holidayDatePicker">انتخاب تاریخ تعطیل (شمسی)</label>
            <JalaliDatePicker
              id="holidayDatePicker"
              invalid={Boolean(holidayErrors.off_date)}
              value={holidayDate}
              onChange={(val) => {
                setHolidayDate(val)
                setHolidayErrors((prev) => ({ ...prev, off_date: undefined }))
              }}
            />
            {holidayErrors.off_date && (
              <p className="field-error" role="alert">
                {holidayErrors.off_date}
              </p>
            )}
          </div>

          <div className="form-group">
            <label htmlFor="holidayReasonInput">علت یا توضیح تعطیلی (اختیاری)</label>
            <input
              id="holidayReasonInput"
              type="text"
              value={holidayReason}
              onChange={(e) => setHolidayReason(e.target.value)}
              placeholder="مثلاً تعطیلی رسمی یا سفر"
            />
            {holidayErrors.reason && (
              <p className="field-error" role="alert">
                {holidayErrors.reason}
              </p>
            )}
          </div>
        </div>

        <div className="admin-form-actions">
          <button type="submit" className="primary-btn" disabled={savingHoliday}>
            {savingHoliday ? 'در حال ثبت...' : 'ثبت تاریخ تعطیل'}
          </button>
        </div>

        <div className="schedule-sublist">
          <h3 className="schedule-sublist-title">تاریخ‌های تعطیل ثبت‌شده</h3>
          {holidays.length === 0 ? (
            <p className="schedule-sublist-empty">هیچ تاریخ تعطیل خاصی ثبت نشده است.</p>
          ) : (
            <div className="schedule-items-grid">
              {holidays.map((h) => (
                <div key={h.id} className="schedule-item-card">
                  <div className="schedule-item-info">
                    <strong>{formatGregorianToJalaliDisplay(h.off_date)}</strong>
                    {h.reason && <span className="schedule-item-reason">({h.reason})</span>}
                  </div>
                  <button
                    type="button"
                    className="cancel-btn small-action-btn"
                    disabled={deletingTimeOffId === h.id}
                    onClick={() => setConfirmDeleteTarget({ ...h, type: 'full_day' })}
                  >
                    {deletingTimeOffId === h.id ? 'در حال حذف...' : 'حذف تعطیلی'}
                  </button>
                </div>
              ))}
            </div>
          )}
        </div>
      </form>

      {/* ۳. ثبت مرخصی چندساعته در یک روز */}
      <form className="booking-form admin-service-form" onSubmit={handleAddLeave} noValidate>
        <h2 className="admin-section-title">ثبت مرخصی چندساعته در یک روز</h2>

        <div className="admin-schedule-four-cols">
          <div className="form-group">
            <label htmlFor="leaveDatePicker">تاریخ مرخصی (شمسی)</label>
            <JalaliDatePicker
              id="leaveDatePicker"
              invalid={Boolean(leaveErrors.off_date)}
              value={leaveDate}
              onChange={(val) => {
                setLeaveDate(val)
                setLeaveErrors((prev) => ({ ...prev, off_date: undefined }))
              }}
            />
            {leaveErrors.off_date && (
              <p className="field-error" role="alert">
                {leaveErrors.off_date}
              </p>
            )}
          </div>

          <div className="form-group">
            <label htmlFor="leaveStartTimeSelect">از ساعت</label>
            <select
              id="leaveStartTimeSelect"
              className={leaveErrors.start_time ? 'input-invalid' : ''}
              value={leaveStartTime}
              onChange={(e) => {
                setLeaveStartTime(e.target.value)
                setLeaveErrors((prev) => ({ ...prev, start_time: undefined }))
              }}
            >
              {SCHEDULE_TIME_OPTIONS.map((t) => (
                <option key={t} value={t}>
                  {toPersianDigits(t)}
                </option>
              ))}
            </select>
            {leaveErrors.start_time && (
              <p className="field-error" role="alert">
                {leaveErrors.start_time}
              </p>
            )}
          </div>

          <div className="form-group">
            <label htmlFor="leaveEndTimeSelect">تا ساعت</label>
            <select
              id="leaveEndTimeSelect"
              className={leaveErrors.end_time ? 'input-invalid' : ''}
              value={leaveEndTime}
              onChange={(e) => {
                setLeaveEndTime(e.target.value)
                setLeaveErrors((prev) => ({ ...prev, end_time: undefined }))
              }}
            >
              {SCHEDULE_TIME_OPTIONS.map((t) => (
                <option key={t} value={t}>
                  {toPersianDigits(t)}
                </option>
              ))}
            </select>
            {leaveErrors.end_time && (
              <p className="field-error" role="alert">
                {leaveErrors.end_time}
              </p>
            )}
          </div>

          <div className="form-group">
            <label htmlFor="leaveReasonInput">توضیح (اختیاری)</label>
            <input
              id="leaveReasonInput"
              type="text"
              value={leaveReason}
              onChange={(e) => setLeaveReason(e.target.value)}
              placeholder="مثلاً کار شخصی"
            />
            {leaveErrors.reason && (
              <p className="field-error" role="alert">
                {leaveErrors.reason}
              </p>
            )}
          </div>
        </div>

        <div className="admin-form-actions">
          <button type="submit" className="primary-btn" disabled={savingLeave}>
            {savingLeave ? 'در حال ثبت...' : 'ثبت مرخصی ساعتی'}
          </button>
        </div>

        <div className="schedule-sublist">
          <h3 className="schedule-sublist-title">مرخصی‌های ساعتی ثبت‌شده</h3>
          {leaves.length === 0 ? (
            <p className="schedule-sublist-empty">هیچ مرخصی ساعتی ثبت نشده است.</p>
          ) : (
            <div className="schedule-items-grid">
              {leaves.map((l) => (
                <div key={l.id} className="schedule-item-card">
                  <div className="schedule-item-info">
                    <strong>{formatGregorianToJalaliDisplay(l.off_date)}</strong>
                    <span className="time-badge">
                      از {toPersianDigits(l.start_time)} تا {toPersianDigits(l.end_time)}
                    </span>
                    {l.reason && <span className="schedule-item-reason">({l.reason})</span>}
                  </div>
                  <button
                    type="button"
                    className="cancel-btn small-action-btn"
                    disabled={deletingTimeOffId === l.id}
                    onClick={() => setConfirmDeleteTarget({ ...l, type: 'hourly' })}
                  >
                    {deletingTimeOffId === l.id ? 'در حال حذف...' : 'حذف مرخصی'}
                  </button>
                </div>
              ))}
            </div>
          )}
        </div>
      </form>
    </div>
  )
}

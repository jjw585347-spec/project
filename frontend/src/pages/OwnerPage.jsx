import { useCallback, useEffect, useState } from 'react'
import JalaliDatePicker from '../components/JalaliDatePicker'
import { apiRequest } from '../utils/api'
import { formatGregorianToJalaliDisplay, getTodayIsoDate } from '../utils/jalali'
import { toPersianDigits } from '../utils/persian'

export default function OwnerPage({ onBack }) {
  const [ownerDate, setOwnerDate] = useState(getTodayIsoDate)
  const [appointments, setAppointments] = useState([])
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState('')
  const [cancellingId, setCancellingId] = useState(null)
  const [confirmTarget, setConfirmTarget] = useState(null)

  const loadAppointments = useCallback(async (targetDate) => {
    if (!targetDate) return
    setLoading(true)
    setError('')
    try {
      const data = await apiRequest(`/api/appointments?date=${encodeURIComponent(targetDate)}`)
      setAppointments(data.appointments || [])
    } catch (err) {
      setError(err.message || 'خطا در دریافت لیست نوبت‌ها')
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => {
    loadAppointments(ownerDate)
  }, [ownerDate, loadAppointments])

  // تایید نهایی لغو نوبت توسط صاحب آرایشگاه و تازه‌سازی خودکار لیست از سرور
  async function handleConfirmCancel() {
    if (!confirmTarget) return
    const appointmentId = confirmTarget.id
    setConfirmTarget(null)
    setCancellingId(appointmentId)
    setError('')

    try {
      await apiRequest(`/api/appointments/${appointmentId}/cancel`, {
        method: 'PATCH',
      })
      // تازه‌سازی کامل لیست نوبت‌ها از سرور پس از لغو
      await loadAppointments(ownerDate)
    } catch (err) {
      setError(err.message || 'خطا در لغو نوبت')
    } finally {
      setCancellingId(null)
    }
  }

  return (
    <div className="owner-container">
      <h1 className="title">لیست نوبت‌های آرایشگاه</h1>

      <div className="owner-toolbar">
        <div className="owner-date-box">
          <label htmlFor="ownerDatePicker">انتخاب روز:</label>
          <JalaliDatePicker id="ownerDatePicker" value={ownerDate} onChange={setOwnerDate} />
        </div>
      </div>

      {error && <p className="error">{error}</p>}

      {/* کادر پرسش تایید قبل از لغو نوبت */}
      {confirmTarget && (
        <div className="confirm-modal-backdrop">
          <div className="confirm-modal-card">
            <h3 className="confirm-modal-title">تأیید لغو نوبت</h3>
            <p className="confirm-modal-text">
              آیا از لغو نوبت <strong>{confirmTarget.customer_name}</strong> در ساعت{' '}
              <strong>{toPersianDigits(confirmTarget.appointment_time)}</strong> مطمئن هستید؟
            </p>
            <div className="confirm-modal-actions">
              <button type="button" className="cancel-btn" onClick={handleConfirmCancel}>
                بله، لغو شود
              </button>
              <button
                type="button"
                className="secondary-btn"
                onClick={() => setConfirmTarget(null)}
              >
                انصراف
              </button>
            </div>
          </div>
        </div>
      )}

      <div className="owner-list-card">
        <div className="owner-list-header">
          <span>نام مشتری</span>
          <span>شماره موبایل</span>
          <span>خدمت</span>
          <span>ساعت</span>
          <span>وضعیت / عملیات</span>
        </div>

        {loading ? (
          <p className="owner-empty">در حال بارگذاری نوبت‌ها...</p>
        ) : appointments.length === 0 ? (
          <p className="owner-empty">
            برای تاریخ {formatGregorianToJalaliDisplay(ownerDate)} هیچ نوبتی ثبت نشده است.
          </p>
        ) : (
          <div className="owner-rows">
            {appointments.map((item) => {
              const isCancelled = item.status === 'cancelled'
              return (
                <div
                  key={item.id}
                  className={`owner-row ${isCancelled ? 'owner-row-cancelled' : ''}`}
                >
                  <div className="owner-cell owner-cell-name">
                    <span className="mobile-label">نام مشتری:</span>
                    <strong>{item.customer_name}</strong>
                  </div>
                  <div className="owner-cell owner-cell-phone">
                    <span className="mobile-label">شماره موبایل:</span>
                    <span>{toPersianDigits(item.customer_phone)}</span>
                  </div>
                  <div className="owner-cell owner-cell-service">
                    <span className="mobile-label">خدمت:</span>
                    <span>{item.service?.name || '-'}</span>
                  </div>
                  <div className="owner-cell owner-cell-time">
                    <span className="mobile-label">ساعت:</span>
                    <span className="time-badge">{toPersianDigits(item.appointment_time)}</span>
                  </div>
                  <div className="owner-cell owner-cell-action">
                    {isCancelled ? (
                      <span className="cancelled-badge">لغو شده</span>
                    ) : (
                      <button
                        type="button"
                        className="cancel-btn"
                        disabled={cancellingId === item.id}
                        onClick={() => setConfirmTarget(item)}
                      >
                        {cancellingId === item.id ? 'در حال لغو...' : 'لغو رزرو'}
                      </button>
                    )}
                  </div>
                </div>
              )
            })}
          </div>
        )}
      </div>

      <div className="page-bottom-nav">
        <button type="button" className="secondary-btn" onClick={onBack}>
          برگشت به صفحه اصلی
        </button>
      </div>
    </div>
  )
}

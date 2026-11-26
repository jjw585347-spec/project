import { useCallback, useEffect, useState } from 'react'
import { apiRequest } from '../utils/api'
import { toEnglishDigits, toPersianDigits } from '../utils/persian'
import AdminScheduleSection from './AdminScheduleSection'
import AdminServicesSection from './AdminServicesSection'

export default function AdminSettingsSection({
  adminToken,
  onUnauthorized,
  onServicesChanged,
  onSettingsChanged,
}) {
  const [loadingGeneral, setLoadingGeneral] = useState(false)
  const [shopName, setShopName] = useState('')
  const [cancelDeadlineHours, setCancelDeadlineHours] = useState('')
  const [generalErrors, setGeneralErrors] = useState({})
  const [generalErrorMsg, setGeneralErrorMsg] = useState('')
  const [generalSuccessMsg, setGeneralSuccessMsg] = useState('')
  const [savingGeneral, setSavingGeneral] = useState(false)

  // وضعیت فرم تغییر نام کاربری، شماره موبایل و رمز ورود مدیر (با پرسیدن رمز فعلی)
  const [adminUsername, setAdminUsername] = useState('')
  const [adminPhone, setAdminPhone] = useState('')
  const [currentPassword, setCurrentPassword] = useState('')
  const [newPassword, setNewPassword] = useState('')
  const [credErrors, setCredErrors] = useState({})
  const [credErrorMsg, setCredErrorMsg] = useState('')
  const [credSuccessMsg, setCredSuccessMsg] = useState('')
  const [savingCreds, setSavingCreds] = useState(false)

  const loadSettings = useCallback(async () => {
    if (!adminToken) return
    setLoadingGeneral(true)
    setGeneralErrorMsg('')
    try {
      const data = await apiRequest('/api/admin/settings', {
        headers: {
          Authorization: `Bearer ${adminToken}`,
        },
      })
      setShopName(data.shop_name || '')
      setCancelDeadlineHours(
        data.cancel_deadline_hours !== undefined && data.cancel_deadline_hours !== null
          ? String(data.cancel_deadline_hours)
          : ''
      )
      if (data.admin?.username) {
        setAdminUsername(data.admin.username)
      }
      if (data.admin?.phone) {
        setAdminPhone(data.admin.phone)
      }
    } catch (err) {
      if (err.status === 401 && onUnauthorized) {
        onUnauthorized()
        return
      }
      setGeneralErrorMsg(err.message || 'خطا در دریافت تنظیمات آرایشگاه')
    } finally {
      setLoadingGeneral(false)
    }
  }, [adminToken, onUnauthorized])

  useEffect(() => {
    loadSettings()
  }, [loadSettings])

  function clearGeneralFieldError(field) {
    setGeneralErrors((prev) => {
      if (!prev[field]) return prev
      const next = { ...prev }
      delete next[field]
      return next
    })
  }

  function clearCredFieldError(field) {
    setCredErrors((prev) => {
      if (!prev[field]) return prev
      const next = { ...prev }
      delete next[field]
      return next
    })
  }

  async function handleSaveGeneral(e) {
    e.preventDefault()
    setGeneralErrorMsg('')
    setGeneralSuccessMsg('')
    setGeneralErrors({})
    setSavingGeneral(true)

    try {
      const data = await apiRequest('/api/admin/settings/general', {
        method: 'PUT',
        headers: {
          Authorization: `Bearer ${adminToken}`,
        },
        body: JSON.stringify({
          shop_name: shopName.trim(),
          cancel_deadline_hours: toEnglishDigits(cancelDeadlineHours).trim(),
        }),
      })
      setShopName(data.shop_name || shopName.trim())
      setCancelDeadlineHours(String(data.cancel_deadline_hours ?? cancelDeadlineHours))
      setGeneralSuccessMsg(
        data.message || 'تنظیمات نام آرایشگاه و مهلت لغو با موفقیت ذخیره شد.'
      )
      if (onSettingsChanged) {
        onSettingsChanged({
          shop_name: data.shop_name,
          cancel_deadline_hours: data.cancel_deadline_hours,
        })
      }
    } catch (err) {
      if (err.status === 401 && onUnauthorized) {
        onUnauthorized()
        return
      }
      if (err.errors && Object.keys(err.errors).length > 0) {
        setGeneralErrors(err.errors)
      } else {
        setGeneralErrorMsg(err.message || 'خطا در ذخیره تنظیمات کلی')
      }
    } finally {
      setSavingGeneral(false)
    }
  }

  async function handleSaveCredentials(e) {
    e.preventDefault()
    setCredErrorMsg('')
    setCredSuccessMsg('')
    setCredErrors({})
    setSavingCreds(true)

    try {
      const data = await apiRequest('/api/admin/settings/credentials', {
        method: 'PUT',
        headers: {
          Authorization: `Bearer ${adminToken}`,
        },
        body: JSON.stringify({
          username: toEnglishDigits(adminUsername).trim(),
          phone: toEnglishDigits(adminPhone).trim(),
          current_password: toEnglishDigits(currentPassword).trim(),
          new_password: toEnglishDigits(newPassword).trim(),
        }),
      })
      if (data.admin?.username) {
        setAdminUsername(data.admin.username)
      }
      if (data.admin?.phone) {
        setAdminPhone(data.admin.phone)
      }
      setCurrentPassword('')
      setNewPassword('')
      setCredSuccessMsg(
        data.message || 'نام کاربری، شماره موبایل و رمز ورود مدیر با موفقیت تغییر کرد.'
      )
    } catch (err) {
      if (err.status === 401 && onUnauthorized) {
        onUnauthorized()
        return
      }
      if (err.errors && Object.keys(err.errors).length > 0) {
        setCredErrors(err.errors)
      } else {
        setCredErrorMsg(err.message || 'خطا در تغییر اطلاعات ورود مدیر')
      }
    } finally {
      setSavingCreds(false)
    }
  }

  return (
    <div className="admin-services-wrapper">
      {/* بخش اول: نام آرایشگاه و مهلت لغو نوبت توسط مشتری */}
      <form className="booking-form admin-service-form" onSubmit={handleSaveGeneral} noValidate>
        <h2 className="admin-section-title">۱. نام آرایشگاه و مهلت لغو نوبت</h2>

        {loadingGeneral ? (
          <p className="owner-empty">در حال بارگذاری تنظیمات...</p>
        ) : (
          <div className="admin-schedule-two-cols">
            <div className="form-group">
              <label htmlFor="settingShopName">نام آرایشگاه</label>
              <input
                id="settingShopName"
                type="text"
                className={generalErrors.shop_name ? 'input-invalid' : ''}
                value={shopName}
                onChange={(e) => {
                  setShopName(e.target.value)
                  clearGeneralFieldError('shop_name')
                }}
                placeholder="نام آرایشگاه را وارد کنید"
              />
              {generalErrors.shop_name && (
                <p className="field-error" role="alert">
                  {generalErrors.shop_name}
                </p>
              )}
            </div>

            <div className="form-group">
              <label htmlFor="settingCancelDeadline">
                مهلت لغو نوبت توسط مشتری (ساعت قبل از شروع نوبت)
              </label>
              <input
                id="settingCancelDeadline"
                type="text"
                inputMode="numeric"
                className={generalErrors.cancel_deadline_hours ? 'input-invalid' : ''}
                value={cancelDeadlineHours}
                onChange={(e) => {
                  setCancelDeadlineHours(e.target.value)
                  clearGeneralFieldError('cancel_deadline_hours')
                }}
                placeholder="تعداد ساعت قبل از نوبت"
              />
              {generalErrors.cancel_deadline_hours && (
                <p className="field-error" role="alert">
                  {generalErrors.cancel_deadline_hours}
                </p>
              )}
            </div>
          </div>
        )}

        {generalErrorMsg && <p className="error">{generalErrorMsg}</p>}
        {generalSuccessMsg && (
          <p className="admin-feedback-success" role="status">
            {toPersianDigits(generalSuccessMsg)}
          </p>
        )}

        <div className="admin-form-actions">
          <button type="submit" className="primary-btn" disabled={savingGeneral || loadingGeneral}>
            {savingGeneral ? 'در حال ذخیره...' : 'ذخیره نام آرایشگاه و مهلت لغو'}
          </button>
        </div>
      </form>

      {/* بخش دوم: تغییر نام کاربری، شماره موبایل و رمز ورود مدیر (با پرسیدن رمز فعلی) */}
      <form
        className="booking-form admin-service-form"
        onSubmit={handleSaveCredentials}
        noValidate
      >
        <h2 className="admin-section-title">
          ۲. تغییر نام کاربری، شماره موبایل و رمز ورود مدیر (با وارد کردن رمز فعلی)
        </h2>

        <div className="admin-schedule-two-cols">
          <div className="form-group">
            <label htmlFor="settingAdminUsername">نام کاربری مدیر</label>
            <input
              id="settingAdminUsername"
              type="text"
              className={credErrors.username ? 'input-invalid' : ''}
              value={adminUsername}
              onChange={(e) => {
                setAdminUsername(e.target.value)
                clearCredFieldError('username')
              }}
              placeholder="نام کاربری مدیر"
            />
            {credErrors.username && (
              <p className="field-error" role="alert">
                {credErrors.username}
              </p>
            )}
          </div>

          <div className="form-group">
            <label htmlFor="settingAdminPhone">شماره موبایل مدیر</label>
            <input
              id="settingAdminPhone"
              type="tel"
              className={credErrors.phone ? 'input-invalid' : ''}
              value={adminPhone}
              onChange={(e) => {
                setAdminPhone(e.target.value)
                clearCredFieldError('phone')
              }}
              placeholder="شماره موبایل ۱۱ رقمی مدیر (مثلاً 09120000000)"
            />
            {credErrors.phone && (
              <p className="field-error" role="alert">
                {credErrors.phone}
              </p>
            )}
          </div>

          <div className="form-group">
            <label htmlFor="settingCurrentPassword">رمز عبور فعلی (الزامی)</label>
            <input
              id="settingCurrentPassword"
              type="password"
              className={credErrors.current_password ? 'input-invalid' : ''}
              value={currentPassword}
              onChange={(e) => {
                setCurrentPassword(e.target.value)
                clearCredFieldError('current_password')
              }}
              placeholder="رمز عبور فعلی خود را وارد کنید"
            />
            {credErrors.current_password && (
              <p className="field-error" role="alert">
                {credErrors.current_password}
              </p>
            )}
          </div>

          <div className="form-group">
            <label htmlFor="settingNewPassword">رمز عبور جدید</label>
            <input
              id="settingNewPassword"
              type="password"
              className={credErrors.new_password ? 'input-invalid' : ''}
              value={newPassword}
              onChange={(e) => {
                setNewPassword(e.target.value)
                clearCredFieldError('new_password')
              }}
              placeholder="رمز عبور جدید را وارد کنید"
            />
            {credErrors.new_password && (
              <p className="field-error" role="alert">
                {credErrors.new_password}
              </p>
            )}
          </div>
        </div>

        {credErrorMsg && <p className="error">{credErrorMsg}</p>}
        {credSuccessMsg && (
          <p className="admin-feedback-success" role="status">
            {credSuccessMsg}
          </p>
        )}

        <div className="admin-form-actions">
          <button type="submit" className="primary-btn" disabled={savingCreds}>
            {savingCreds ? 'در حال ذخیره...' : 'ذخیره نام کاربری، شماره موبایل و رمز جدید'}
          </button>
        </div>
      </form>

      {/* بخش سوم: ساعت کاری هفته، تعطیلی‌ها و مرخصی‌ها */}
      <AdminScheduleSection adminToken={adminToken} onUnauthorized={onUnauthorized} />

      {/* بخش چهارم: مدیریت خدمات آرایشگاه */}
      <AdminServicesSection
        adminToken={adminToken}
        onUnauthorized={onUnauthorized}
        onServicesChanged={onServicesChanged}
      />
    </div>
  )
}

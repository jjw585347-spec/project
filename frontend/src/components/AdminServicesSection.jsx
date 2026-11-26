import { useCallback, useEffect, useState } from 'react'
import { EmptyState, ErrorState, LoadingSkeleton, TruncatedText } from '../ui'
import { apiRequest } from '../utils/api'
import { formatPrice, toPersianDigits } from '../utils/persian'

export default function AdminServicesSection({
  adminToken,
  onUnauthorized,
  onServicesChanged,
}) {
  const [services, setServices] = useState([])
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState('')
  const [successMessage, setSuccessMessage] = useState('')

  const [editingId, setEditingId] = useState(null)
  const [name, setName] = useState('')
  const [price, setPrice] = useState('')
  const [duration, setDuration] = useState('')
  const [fieldErrors, setFieldErrors] = useState({})
  const [submitting, setSubmitting] = useState(false)
  const [busyServiceId, setBusyServiceId] = useState(null)
  const [confirmAction, setConfirmAction] = useState(null)

  function clearFieldError(field) {
    setFieldErrors((prev) => {
      if (!prev[field]) return prev
      const next = { ...prev }
      delete next[field]
      return next
    })
  }

  const loadAdminServices = useCallback(async () => {
    if (!adminToken) return
    setLoading(true)
    setError('')
    try {
      const data = await apiRequest('/api/admin/services', {
        headers: {
          Authorization: `Bearer ${adminToken}`,
        },
      })
      setServices(Array.isArray(data.services) ? data.services : [])
    } catch (err) {
      if (err.status === 401 && onUnauthorized) {
        onUnauthorized()
        return
      }
      setError(err.message || 'خطا در دریافت لیست خدمات')
    } finally {
      setLoading(false)
    }
  }, [adminToken, onUnauthorized])

  useEffect(() => {
    loadAdminServices()
  }, [loadAdminServices])

  function resetForm() {
    setEditingId(null)
    setName('')
    setPrice('')
    setDuration('')
    setFieldErrors({})
  }

  function handleStartEdit(service) {
    setEditingId(service.id)
    setName(service.name || '')
    setPrice(String(service.price ?? ''))
    setDuration(String(service.duration ?? ''))
    setFieldErrors({})
    setError('')
    setSuccessMessage('')
  }

  async function handleSubmit(e) {
    e.preventDefault()
    setError('')
    setSuccessMessage('')
    setFieldErrors({})
    setSubmitting(true)

    const isEditing = editingId !== null
    const url = isEditing ? `/api/admin/services/${editingId}` : '/api/admin/services'
    const method = isEditing ? 'PUT' : 'POST'

    try {
      const data = await apiRequest(url, {
        method,
        headers: {
          Authorization: `Bearer ${adminToken}`,
        },
        body: JSON.stringify({
          name: name.trim(),
          price: price.trim(),
          duration: duration.trim(),
        }),
      })
      setSuccessMessage(
        data.message || (isEditing ? 'خدمت با موفقیت ویرایش شد.' : 'خدمت جدید اضافه شد.')
      )
      resetForm()
      await loadAdminServices()
      if (onServicesChanged) {
        onServicesChanged()
      }
    } catch (err) {
      if (err.status === 401 && onUnauthorized) {
        onUnauthorized()
        return
      }
      if (err.errors && Object.keys(err.errors).length > 0) {
        setFieldErrors(err.errors)
      } else {
        setError(err.message || 'خطا در ذخیره اطلاعات خدمت')
      }
    } finally {
      setSubmitting(false)
    }
  }

  async function handleToggleStatus(service) {
    setBusyServiceId(service.id)
    setError('')
    setSuccessMessage('')

    try {
      const data = await apiRequest(`/api/admin/services/${service.id}/status`, {
        method: 'PATCH',
        headers: {
          Authorization: `Bearer ${adminToken}`,
        },
        body: JSON.stringify({
          is_active: !service.is_active,
        }),
      })
      setSuccessMessage(data.message || 'وضعیت خدمت تغییر کرد.')
      await loadAdminServices()
      if (onServicesChanged) {
        onServicesChanged()
      }
    } catch (err) {
      if (err.status === 401 && onUnauthorized) {
        onUnauthorized()
        return
      }
      setError(err.message || 'خطا در تغییر وضعیت خدمت')
    } finally {
      setBusyServiceId(null)
    }
  }

  async function handleDeleteService(service) {
    setBusyServiceId(service.id)
    setError('')
    setSuccessMessage('')

    try {
      const data = await apiRequest(`/api/admin/services/${service.id}`, {
        method: 'DELETE',
        headers: {
          Authorization: `Bearer ${adminToken}`,
        },
      })
      setSuccessMessage(data.message || 'خدمت با موفقیت حذف شد.')
      if (editingId === service.id) {
        resetForm()
      }
      await loadAdminServices()
      if (onServicesChanged) {
        onServicesChanged()
      }
    } catch (err) {
      if (err.status === 401 && onUnauthorized) {
        onUnauthorized()
        return
      }
      setError(err.message || 'خطا در حذف خدمت')
    } finally {
      setBusyServiceId(null)
    }
  }

  async function handleConfirmModalAction() {
    if (!confirmAction) return
    const { type, service } = confirmAction
    setConfirmAction(null)
    if (type === 'delete') {
      await handleDeleteService(service)
    } else if (type === 'deactivate') {
      await handleToggleStatus(service)
    }
  }

  return (
    <div className="admin-services-wrapper">
      {confirmAction && (
        <div className="confirm-modal-backdrop">
          <div className="confirm-modal-card">
            <h3 className="confirm-modal-title">
              {confirmAction.type === 'delete' ? 'تأیید حذف خدمت' : 'تأیید غیرفعال کردن خدمت'}
            </h3>
            <p className="confirm-modal-text">
              {confirmAction.type === 'delete' ? (
                <>
                  آیا از حذف کامل خدمت <strong>{confirmAction.service.name}</strong> مطمئن هستید؟
                </>
              ) : (
                <>
                  آیا از غیرفعال کردن خدمت <strong>{confirmAction.service.name}</strong> مطمئن
                  هستید؟
                </>
              )}
            </p>
            <div className="confirm-modal-actions">
              <button type="button" className="cancel-btn" onClick={handleConfirmModalAction}>
                {confirmAction.type === 'delete' ? 'بله، حذف شود' : 'بله، غیرفعال شود'}
              </button>
              <button
                type="button"
                className="secondary-btn"
                onClick={() => setConfirmAction(null)}
              >
                انصراف
              </button>
            </div>
          </div>
        </div>
      )}

      <form className="booking-form admin-service-form" onSubmit={handleSubmit} noValidate>
        <h2 className="admin-section-title">
          {editingId !== null ? 'ویرایش خدمت' : 'افزودن خدمت جدید'}
        </h2>

        <div className="admin-service-fields">
          <div className="form-group">
            <label htmlFor="serviceNameInput">نام خدمت</label>
            <input
              id="serviceNameInput"
              type="text"
              className={fieldErrors.name ? 'input-invalid' : ''}
              value={name}
              onChange={(e) => {
                setName(e.target.value)
                clearFieldError('name')
              }}
              placeholder="مثلاً اصلاح مو"
            />
            {fieldErrors.name && (
              <p className="field-error" role="alert">
                {fieldErrors.name}
              </p>
            )}
          </div>

          <div className="form-group">
            <label htmlFor="servicePriceInput">قیمت (تومان)</label>
            <input
              id="servicePriceInput"
              type="text"
              inputMode="numeric"
              className={fieldErrors.price ? 'input-invalid' : ''}
              value={price}
              onChange={(e) => {
                setPrice(e.target.value)
                clearFieldError('price')
              }}
              placeholder="مثلاً ۲۵۰۰۰۰"
            />
            {fieldErrors.price && (
              <p className="field-error" role="alert">
                {fieldErrors.price}
              </p>
            )}
          </div>

          <div className="form-group">
            <label htmlFor="serviceDurationInput">مدت زمان (دقیقه)</label>
            <input
              id="serviceDurationInput"
              type="text"
              inputMode="numeric"
              className={fieldErrors.duration ? 'input-invalid' : ''}
              value={duration}
              onChange={(e) => {
                setDuration(e.target.value)
                clearFieldError('duration')
              }}
              placeholder="مثلاً ۲۰ یا ۳۰"
            />
            {fieldErrors.duration && (
              <p className="field-error" role="alert">
                {fieldErrors.duration}
              </p>
            )}
          </div>
        </div>

        <div className="admin-form-actions">
          <button type="submit" className="primary-btn" disabled={submitting}>
            {submitting
              ? 'در حال ذخیره...'
              : editingId !== null
                ? 'ذخیره ویرایش'
                : 'افزودن خدمت'}
          </button>

          {editingId !== null && (
            <button type="button" className="secondary-btn" onClick={resetForm}>
              انصراف از ویرایش
            </button>
          )}
        </div>
      </form>

      {error && <ErrorState message={error} onRetry={loadAdminServices} />}
      {successMessage && <p className="admin-feedback-success">{successMessage}</p>}

      <div className="owner-list-card">
        <div className="owner-list-header admin-services-grid">
          <span>نام خدمت</span>
          <span>قیمت</span>
          <span>مدت زمان</span>
          <span>وضعیت</span>
          <span>عملیات</span>
        </div>

        {loading ? (
          <LoadingSkeleton
            variant="rows"
            count={4}
            label="در حال بارگذاری خدمات..."
          />
        ) : services.length === 0 ? (
          <EmptyState message="هیچ خدمتی در سیستم ثبت نشده است." />
        ) : (
          <div className="owner-rows">
            {services.map((service) => {
              const isInactive = !service.is_active
              const hasAppointments = Number(service.appointments_count || 0) > 0
              const isBusy = busyServiceId === service.id

              return (
                <div
                  key={service.id}
                  className={`owner-row admin-services-grid ${
                    isInactive ? 'owner-row-cancelled' : ''
                  }`}
                >
                  <div className="owner-cell">
                    <span className="mobile-label">نام خدمت:</span>
                    <TruncatedText as="strong" text={service.name} />
                  </div>

                  <div className="owner-cell">
                    <span className="mobile-label">قیمت:</span>
                    <span>{formatPrice(service.price)} تومان</span>
                  </div>

                  <div className="owner-cell">
                    <span className="mobile-label">مدت زمان:</span>
                    <span className="time-badge">{toPersianDigits(service.duration)} دقیقه</span>
                  </div>

                  <div className="owner-cell">
                    <span className="mobile-label">وضعیت:</span>
                    {service.is_active ? (
                      <span className="active-badge">فعال</span>
                    ) : (
                      <span className="cancelled-badge">غیرفعال</span>
                    )}
                  </div>

                  <div className="owner-cell admin-service-actions">
                    <button
                      type="button"
                      className="secondary-btn small-action-btn"
                      disabled={isBusy}
                      onClick={() => handleStartEdit(service)}
                    >
                      ویرایش
                    </button>

                    <button
                      type="button"
                      className={
                        service.is_active
                          ? 'cancel-btn small-action-btn'
                          : 'primary-btn small-action-btn'
                      }
                      disabled={isBusy}
                      onClick={() =>
                        service.is_active
                          ? setConfirmAction({ type: 'deactivate', service })
                          : handleToggleStatus(service)
                      }
                    >
                      {service.is_active ? 'غیرفعال کردن' : 'فعال کردن'}
                    </button>

                    {!hasAppointments ? (
                      <button
                        type="button"
                        className="cancel-btn small-action-btn"
                        disabled={isBusy}
                        onClick={() => setConfirmAction({ type: 'delete', service })}
                      >
                        حذف
                      </button>
                    ) : (
                      <span className="protected-service-hint">
                        دارای نوبت ({toPersianDigits(service.appointments_count)})
                      </span>
                    )}
                  </div>
                </div>
              )
            })}
          </div>
        )}
      </div>
    </div>
  )
}

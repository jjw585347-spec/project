const OFFLINE_ERROR_MESSAGE =
  'ارتباط با سرور برقرار نشد. لطفاً مطمئن شوید سرور بک‌اند روشن است.'

// بررسی اینکه آیا متن حاوی پیام فارسی است یا خیر
function hasPersianCharacters(text) {
  return /[\u0600-\u06FF]/.test(String(text || ''))
}

// یکسان‌سازی ساختار پیام‌های خطای هر فیلد از پاسخ 422 بک‌اند
function normalizeFieldErrors(rawErrors) {
  if (!rawErrors || typeof rawErrors !== 'object') {
    return null
  }
  const normalized = {}
  for (const [field, value] of Object.entries(rawErrors)) {
    const firstMessage = Array.isArray(value) ? value[0] : value
    if (typeof firstMessage === 'string' && firstMessage.trim()) {
      normalized[field] = firstMessage.trim()
    }
  }
  return Object.keys(normalized).length > 0 ? normalized : null
}

/**
 * ارسال درخواست به بک‌اند با مدیریت خطای فارسی (حتی در زمان خاموش بودن بک‌اند)
 */
export async function apiRequest(url, options = {}) {
  let response
  try {
    response = await fetch(url, {
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        ...(options.headers || {}),
      },
      ...options,
    })
  } catch {
    throw new Error(OFFLINE_ERROR_MESSAGE)
  }

  let data = null
  try {
    const text = await response.text()
    data = text ? JSON.parse(text) : null
  } catch {
    throw new Error(OFFLINE_ERROR_MESSAGE)
  }

  if (!response.ok) {
    const fieldErrors = normalizeFieldErrors(data?.errors)

    if (data?.message && hasPersianCharacters(data.message)) {
      const error = new Error(data.message)
      error.status = response.status
      error.errors = fieldErrors
      throw error
    }
    if (fieldErrors) {
      const firstPersianFieldError = Object.values(fieldErrors).find((msg) =>
        hasPersianCharacters(msg)
      )
      const error = new Error(
        firstPersianFieldError || 'خطایی در اعتبارسنجی اطلاعات رخ داد.'
      )
      error.status = response.status
      error.errors = fieldErrors
      throw error
    }
    if (response.status >= 500) {
      throw new Error(OFFLINE_ERROR_MESSAGE)
    }
    throw new Error('خطایی در انجام عملیات رخ داد. لطفاً دوباره تلاش کنید.')
  }

  if (data === null) {
    throw new Error(OFFLINE_ERROR_MESSAGE)
  }

  return data
}

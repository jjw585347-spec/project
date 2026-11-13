const OFFLINE_ERROR_MESSAGE =
  'ارتباط با سرور برقرار نشد. لطفاً مطمئن شوید سرور بک‌اند روشن است.'

// بررسی اینکه آیا متن حاوی پیام فارسی است یا خیر
function hasPersianCharacters(text) {
  return /[\u0600-\u06FF]/.test(String(text || ''))
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
    if (data?.message && hasPersianCharacters(data.message)) {
      throw new Error(data.message)
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

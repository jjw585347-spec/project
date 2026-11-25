// نمایش قیمت با جداکننده هزارگان و اعداد فارسی
export function formatPrice(price) {
  return Number(price).toLocaleString('fa-IR')
}

// تبدیل اعداد انگلیسی به فارسی
export function toPersianDigits(val) {
  return String(val ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d])
}

// تبدیل اعداد فارسی و عربی به انگلیسی
export function toEnglishDigits(val) {
  return String(val ?? '')
    .replace(/[۰-۹]/g, (d) => String('۰۱۲۳۴۵۶۷۸۹'.indexOf(d)))
    .replace(/[٠-٩]/g, (d) => String('٠١٢٣٤٥٦٧٨٩'.indexOf(d)))
}

// ساخت لیست ساعت‌های شروع پایه در بازه کاری پیش‌فرض از ۱۰:۰۰ تا ۱۹:۳۰ (پایان کار ۲۰:۰۰ طبق R6)
export function generateTimeSlots() {
  const slots = []
  for (let hour = 10; hour < 20; hour++) {
    const h = String(hour).padStart(2, '0')
    slots.push(`${h}:00`)
    slots.push(`${h}:30`)
  }
  return slots
}

export const TIME_SLOTS = generateTimeSlots()

// تبدیل رشته HH:MM به دقیقه از ابتدای روز
export function timeToMinutes(timeStr) {
  if (!timeStr || typeof timeStr !== 'string' || !timeStr.includes(':')) {
    return 0
  }
  const [h, m] = timeStr.split(':').map((v) => Number(v) || 0)
  return h * 60 + m
}

// تبدیل دقیقه از ابتدای روز به رشته HH:MM
export function minutesToTime(totalMinutes) {
  const h = String(Math.floor(totalMinutes / 60)).padStart(2, '0')
  const m = String(totalMinutes % 60).padStart(2, '0')
  return `${h}:${m}`
}

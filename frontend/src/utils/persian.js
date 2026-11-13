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

// ساخت لیست ساعت‌های کاری از ۱۰:۰۰ تا ۲۰:۰۰ با فاصله ۳۰ دقیقه‌ای
export function generateTimeSlots() {
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

export const TIME_SLOTS = generateTimeSlots()

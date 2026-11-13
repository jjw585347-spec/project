import { toPersianDigits } from './persian'

// گرفتن تاریخ امروز به فرمت YYYY-MM-DD
export function getTodayIsoDate() {
  const now = new Date()
  const gy = now.getFullYear()
  const gm = String(now.getMonth() + 1).padStart(2, '0')
  const gd = String(now.getDate()).padStart(2, '0')
  return `${gy}-${gm}-${gd}`
}

export const JALALI_MONTHS = [
  'فروردین',
  'اردیبهشت',
  'خرداد',
  'تیر',
  'مرداد',
  'شهریور',
  'مهر',
  'آبان',
  'آذر',
  'دی',
  'بهمن',
  'اسفند',
]

export const WEEK_DAYS = ['ش', 'ی', 'د', 'س', 'چ', 'پ', 'ج']

// تبدیل تاریخ میلادی به شمسی
export function gregorianToJalali(gy, gm, gd) {
  const g_d_m = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334]
  const gy2 = gm > 2 ? gy + 1 : gy
  let days =
    355666 +
    365 * gy +
    Math.floor((gy2 + 3) / 4) -
    Math.floor((gy2 + 99) / 100) +
    Math.floor((gy2 + 399) / 400) +
    gd +
    g_d_m[gm - 1]
  let jy = -1595 + 33 * Math.floor(days / 12053)
  days %= 12053
  jy += 4 * Math.floor(days / 1461)
  days %= 1461
  if (days > 365) {
    jy += Math.floor((days - 1) / 365)
    days = (days - 1) % 365
  }
  const jm = days < 186 ? 1 + Math.floor(days / 31) : 7 + Math.floor((days - 186) / 30)
  const jd = 1 + (days < 186 ? days % 31 : (days - 186) % 30)
  return [jy, jm, jd]
}

// تبدیل تاریخ شمسی به میلادی
export function jalaliToGregorian(jy, jm, jd) {
  const jy1 = jy + 1595
  let days =
    -355668 +
    365 * jy1 +
    Math.floor(jy1 / 33) * 8 +
    Math.floor(((jy1 % 33) + 3) / 4) +
    jd +
    (jm < 7 ? (jm - 1) * 31 : (jm - 7) * 30 + 186)
  let gy = 400 * Math.floor(days / 146097)
  days %= 146097
  if (days > 36524) {
    gy += 100 * Math.floor(--days / 36524)
    days %= 36524
    if (days >= 365) days++
  }
  gy += 4 * Math.floor(days / 1461)
  days %= 1461
  if (days > 365) {
    gy += Math.floor((days - 1) / 365)
    days = (days - 1) % 365
  }
  let gd = days + 1
  const sal_a = [
    0,
    31,
    (gy % 4 === 0 && gy % 100 !== 0) || gy % 400 === 0 ? 29 : 28,
    31,
    30,
    31,
    30,
    31,
    31,
    30,
    31,
    30,
    31,
  ]
  let gm
  for (gm = 0; gm < 13 && gd > sal_a[gm]; gm++) {
    gd -= sal_a[gm]
  }
  return [gy, gm, gd]
}

// تعداد روزهای یک ماه شمسی
export function getJalaliMonthLength(jy, jm) {
  if (jm <= 6) return 31
  if (jm <= 11) return 30
  const [gy1, gm1, gd1] = jalaliToGregorian(jy, 12, 30)
  const [jyCheck] = gregorianToJalali(gy1, gm1, gd1)
  return jyCheck === jy ? 30 : 29
}

// تبدیل رشته YYYY-MM-DD میلادی به نمایش شمسی (مثلاً ۱۴۰۵/۰۸/۲۱)
export function formatGregorianToJalaliDisplay(dateStr) {
  if (!dateStr) return ''
  const parts = String(dateStr).split('T')[0].split('-').map(Number)
  if (parts.length !== 3) return dateStr
  const [jy, jm, jd] = gregorianToJalali(parts[0], parts[1], parts[2])
  const y = toPersianDigits(jy)
  const m = toPersianDigits(String(jm).padStart(2, '0'))
  const d = toPersianDigits(String(jd).padStart(2, '0'))
  return `${y}/${m}/${d}`
}

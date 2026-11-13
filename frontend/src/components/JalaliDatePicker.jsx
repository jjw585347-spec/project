import { useEffect, useRef, useState } from 'react'
import {
  formatGregorianToJalaliDisplay,
  getJalaliMonthLength,
  gregorianToJalali,
  JALALI_MONTHS,
  jalaliToGregorian,
  WEEK_DAYS,
} from '../utils/jalali'
import { toPersianDigits } from '../utils/persian'

export default function JalaliDatePicker({ id = 'appointmentDate', value, onChange }) {
  const now = new Date()
  const [todayJy, todayJm, todayJd] = gregorianToJalali(
    now.getFullYear(),
    now.getMonth() + 1,
    now.getDate()
  )

  const [isOpen, setIsOpen] = useState(false)
  const [viewYear, setViewYear] = useState(todayJy)
  const [viewMonth, setViewMonth] = useState(todayJm)
  const containerRef = useRef(null)

  useEffect(() => {
    function handleClickOutside(e) {
      if (containerRef.current && !containerRef.current.contains(e.target)) {
        setIsOpen(false)
      }
    }
    document.addEventListener('mousedown', handleClickOutside)
    return () => document.removeEventListener('mousedown', handleClickOutside)
  }, [])

  const [firstGy, firstGm, firstGd] = jalaliToGregorian(viewYear, viewMonth, 1)
  const firstDayOfWeek = (new Date(firstGy, firstGm - 1, firstGd).getDay() + 1) % 7
  const daysInMonth = getJalaliMonthLength(viewYear, viewMonth)

  let selectedJy = null
  let selectedJm = null
  let selectedJd = null
  if (value) {
    const parts = value.split('-').map(Number)
    if (parts.length === 3) {
      ;[selectedJy, selectedJm, selectedJd] = gregorianToJalali(parts[0], parts[1], parts[2])
    }
  }

  function handlePrevMonth() {
    if (viewMonth === 1) {
      setViewMonth(12)
      setViewYear((y) => y - 1)
    } else {
      setViewMonth((m) => m - 1)
    }
  }

  function handleNextMonth() {
    if (viewMonth === 12) {
      setViewMonth(1)
      setViewYear((y) => y + 1)
    } else {
      setViewMonth((m) => m + 1)
    }
  }

  function handleSelectDay(day) {
    const [gy, gm, gd] = jalaliToGregorian(viewYear, viewMonth, day)
    const isoDate = `${gy}-${String(gm).padStart(2, '0')}-${String(gd).padStart(2, '0')}`
    onChange(isoDate)
    setIsOpen(false)
  }

  function handleSelectToday() {
    setViewYear(todayJy)
    setViewMonth(todayJm)
    const [gy, gm, gd] = jalaliToGregorian(todayJy, todayJm, todayJd)
    const isoDate = `${gy}-${String(gm).padStart(2, '0')}-${String(gd).padStart(2, '0')}`
    onChange(isoDate)
    setIsOpen(false)
  }

  const cells = []
  for (let i = 0; i < firstDayOfWeek; i++) {
    cells.push(null)
  }
  for (let d = 1; d <= daysInMonth; d++) {
    cells.push(d)
  }

  return (
    <div className="jalali-datepicker" ref={containerRef}>
      <input
        id={id}
        type="text"
        readOnly
        value={value ? formatGregorianToJalaliDisplay(value) : ''}
        placeholder="انتخاب تاریخ شمسی (مثلاً ۱۴۰۵/۰۸/۲۱)"
        onClick={() => setIsOpen((o) => !o)}
        className="jalali-input"
        required
      />
      {isOpen && (
        <div className="jalali-popover">
          <div className="jalali-header">
            <button type="button" className="jalali-nav-btn" onClick={handlePrevMonth}>
              ماه قبل
            </button>
            <span className="jalali-month-title">
              {JALALI_MONTHS[viewMonth - 1]} {toPersianDigits(viewYear)}
            </span>
            <button type="button" className="jalali-nav-btn" onClick={handleNextMonth}>
              ماه بعد
            </button>
          </div>

          <div className="jalali-weekdays">
            {WEEK_DAYS.map((w) => (
              <span key={w}>{w}</span>
            ))}
          </div>

          <div className="jalali-days-grid">
            {cells.map((day, idx) => {
              if (!day) {
                return <span key={`empty-${idx}`} className="jalali-day-empty" />
              }
              const isSelected =
                selectedJy === viewYear && selectedJm === viewMonth && selectedJd === day
              const isToday = todayJy === viewYear && todayJm === viewMonth && todayJd === day

              return (
                <button
                  key={day}
                  type="button"
                  className={`jalali-day-btn ${isSelected ? 'selected' : ''} ${
                    isToday ? 'today' : ''
                  }`}
                  onClick={() => handleSelectDay(day)}
                >
                  {toPersianDigits(day)}
                </button>
              )
            })}
          </div>

          <div className="jalali-footer">
            <button type="button" className="jalali-today-btn" onClick={handleSelectToday}>
              برو به امروز ({toPersianDigits(todayJd)} {JALALI_MONTHS[todayJm - 1]}{' '}
              {toPersianDigits(todayJy)})
            </button>
          </div>
        </div>
      )}
    </div>
  )
}

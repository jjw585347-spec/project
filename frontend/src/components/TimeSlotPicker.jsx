import { TIME_SLOTS, toPersianDigits } from '../utils/persian'

/**
 * نمایش بازه‌های زمانی ۳۰ دقیقه‌ای (۱۰:۰۰ تا ۲۰:۰۰) به صورت کارت‌های کوچک قابل کلیک
 */
export default function TimeSlotPicker({ value, onChange, bookedSlots = [] }) {
  return (
    <div className="time-slots-grid" role="radiogroup" aria-label="انتخاب ساعت نوبت">
      {TIME_SLOTS.map((slot) => {
        const isSelected = value === slot
        const isBooked = bookedSlots.includes(slot)

        return (
          <button
            key={slot}
            type="button"
            disabled={isBooked}
            onClick={() => onChange(slot)}
            className={`time-slot-card ${isSelected ? 'selected' : ''} ${
              isBooked ? 'booked' : ''
            }`}
            title={isBooked ? 'این ساعت رزرو شده است' : `انتخاب ساعت ${toPersianDigits(slot)}`}
          >
            <span className="time-slot-text">{toPersianDigits(slot)}</span>
            {isBooked && <span className="time-slot-badge">رزرو شده</span>}
          </button>
        )
      })}
    </div>
  )
}

import { TIME_SLOTS, toPersianDigits } from '../utils/persian'

/**
 * نمایش بازه‌های زمانی ۳۰ دقیقه‌ای کاری روز انتخاب‌شده به صورت کارت‌های کوچک قابل کلیک
 * ساعت‌های رزروشده یا داخل مرخصی ساعتی (R7) غیرفعال می‌شوند
 */
export default function TimeSlotPicker({
  value,
  onChange,
  bookedSlots = [],
  leaveSlots = [],
  availableSlots = TIME_SLOTS,
  isDayClosed = false,
  closeReason = '',
}) {
  if (isDayClosed) {
    return (
      <div className="day-closed-notice" role="alert">
        {toPersianDigits(closeReason || 'آرایشگاه در این روز تعطیل است و امکان ثبت نوبت وجود ندارد.')}
      </div>
    )
  }

  const slotsToRender = Array.isArray(availableSlots) && availableSlots.length > 0
    ? availableSlots
    : TIME_SLOTS

  return (
    <div className="time-slots-grid" role="radiogroup" aria-label="انتخاب ساعت نوبت">
      {slotsToRender.map((slot) => {
        const isSelected = value === slot
        const isBooked = bookedSlots.includes(slot)
        const isOnLeave = !isBooked && leaveSlots.includes(slot)
        const isDisabled = isBooked || isOnLeave

        return (
          <button
            key={slot}
            type="button"
            disabled={isDisabled}
            onClick={() => onChange(slot)}
            className={`time-slot-card ${isSelected ? 'selected' : ''} ${
              isDisabled ? 'booked' : ''
            }`}
            title={
              isBooked
                ? 'این ساعت رزرو شده است'
                : isOnLeave
                  ? 'در این ساعت مرخصی ثبت شده است'
                  : `انتخاب ساعت ${toPersianDigits(slot)}`
            }
          >
            <span className="time-slot-text">{toPersianDigits(slot)}</span>
            {isBooked && <span className="time-slot-badge">رزرو شده</span>}
            {isOnLeave && <span className="time-slot-badge">مرخصی</span>}
          </button>
        )
      })}
    </div>
  )
}

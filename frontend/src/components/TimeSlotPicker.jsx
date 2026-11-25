import { minutesToTime, timeToMinutes, toPersianDigits } from '../utils/persian'

/**
 * نمایش فقط بازه‌های زمانی ۳۰ دقیقه‌ای که خدمات انتخاب‌شده مشتری کامل در آن‌ها جا می‌شوند (R5 تا R8):
 * - داخل ساعت کاری روز (پایان نوبت <= پایان ساعت کاری)
 * - بیرون از تعطیلی و مرخصی ساعتی
 * - بدون هم‌پوشانی با نوبت‌های فعال دیگر
 */
export default function TimeSlotPicker({
  value,
  onChange,
  fittingSlots = [],
  totalDuration = 30,
  hasSelectedDate = false,
  hasSelectedServices = true,
  isDayClosed = false,
  closeReason = '',
}) {
  if (!hasSelectedServices) {
    return (
      <div className="slot-hint-notice" role="status">
        ابتدا حداقل یک خدمت را از لیست بالا انتخاب کنید تا زمان‌های قابل رزرو محاسبه شوند.
      </div>
    )
  }

  if (!hasSelectedDate) {
    return (
      <div className="slot-hint-notice" role="status">
        لطفاً ابتدا روز نوبت را از تقویم بالا انتخاب کنید تا زمان‌هایی که خدمات انتخابی شما در آن جا می‌شوند نمایش داده شود.
      </div>
    )
  }

  if (isDayClosed) {
    return (
      <div className="day-closed-notice" role="alert">
        {toPersianDigits(closeReason || 'آرایشگاه در این روز تعطیل است و امکان ثبت نوبت وجود ندارد.')}
      </div>
    )
  }

  if (!Array.isArray(fittingSlots) || fittingSlots.length === 0) {
    return (
      <div className="day-closed-notice" role="alert">
        {toPersianDigits(
          `در این روز هیچ بازه زمانی خالی که خدمات انتخابی شما (${totalDuration} دقیقه) کامل در آن جا شود وجود ندارد. لطفاً روز دیگری را انتخاب کنید.`
        )}
      </div>
    )
  }

  return (
    <div className="time-slots-grid" role="radiogroup" aria-label="انتخاب ساعت نوبت">
      {fittingSlots.map((slot) => {
        const isSelected = value === slot
        const endTime = minutesToTime(timeToMinutes(slot) + Math.max(1, Number(totalDuration) || 30))

        return (
          <button
            key={slot}
            type="button"
            onClick={() => onChange(slot)}
            className={`time-slot-card ${isSelected ? 'selected' : ''}`}
            title={toPersianDigits(`انتخاب ساعت ${slot} تا ${endTime} (${totalDuration} دقیقه)`)}
          >
            <span className="time-slot-text">{toPersianDigits(slot)}</span>
            <span className="time-slot-end-hint">
              تا {toPersianDigits(endTime)}
            </span>
          </button>
        )
      })}
    </div>
  )
}

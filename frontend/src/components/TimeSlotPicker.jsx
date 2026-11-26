import { EmptyState, ErrorState, LoadingSkeleton } from '../ui'
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
  loading = false,
  error = '',
  onRetry,
}) {
  if (!hasSelectedServices) {
    return (
      <EmptyState message="ابتدا حداقل یک خدمت را از لیست بالا انتخاب کنید تا زمان‌های قابل رزرو محاسبه شوند." />
    )
  }

  if (!hasSelectedDate) {
    return (
      <EmptyState message="لطفاً ابتدا روز نوبت را از تقویم بالا انتخاب کنید تا زمان‌هایی که خدمات انتخابی شما در آن جا می‌شوند نمایش داده شود." />
    )
  }

  if (loading) {
    return (
      <LoadingSkeleton
        variant="slots"
        count={8}
        label="در حال بررسی زمان‌های خالی این روز..."
      />
    )
  }

  if (error) {
    return <ErrorState message={error} onRetry={onRetry} />
  }

  if (isDayClosed) {
    return (
      <EmptyState
        message={closeReason || 'آرایشگاه در این روز تعطیل است و امکان ثبت نوبت وجود ندارد.'}
      />
    )
  }

  if (!Array.isArray(fittingSlots) || fittingSlots.length === 0) {
    return (
      <EmptyState
        message={`در این روز هیچ بازه زمانی خالی که خدمات انتخابی شما (${toPersianDigits(
          totalDuration
        )} دقیقه) کامل در آن جا شود وجود ندارد. لطفاً روز دیگری را انتخاب کنید.`}
      />
    )
  }

  return (
    <div className="time-slots-grid" role="radiogroup" aria-label="انتخاب ساعت نوبت">
      {fittingSlots.map((slot) => {
        const isSelected = value === slot
        const endTime = minutesToTime(
          timeToMinutes(slot) + Math.max(1, Number(totalDuration) || 30)
        )

        return (
          <button
            key={slot}
            type="button"
            onClick={() => onChange(slot)}
            className={`time-slot-card ${isSelected ? 'selected' : ''}`}
            title={toPersianDigits(`انتخاب ساعت ${slot} تا ${endTime} (${totalDuration} دقیقه)`)}
          >
            <span className="time-slot-text">{toPersianDigits(slot)}</span>
            <span className="time-slot-end-hint">تا {toPersianDigits(endTime)}</span>
          </button>
        )
      })}
    </div>
  )
}

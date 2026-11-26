import { toPersianDigits } from '../utils/persian'

/**
 * بررسی وجود حروف فارسی در پیام خطا تا هرگز متن خام انگلیسی به کاربر نشان داده نشود
 */
function ensurePersianMessage(msg, fallback = 'خطایی در دریافت اطلاعات رخ داد. لطفاً دوباره تلاش کنید.') {
  const str = String(msg || '').trim()
  if (!str) return fallback
  return /[\u0600-\u06FF]/.test(str) ? toPersianDigits(str) : fallback
}

/**
 * اسکلت بارگذاری یکپارچه برای کارت‌ها، ردیف‌های جدول و زمان‌های خالی
 */
export function LoadingSkeleton({
  variant = 'rows',
  count = 4,
  label = 'در حال بارگذاری...',
}) {
  const items = Array.from({ length: count }, (_, idx) => idx)

  return (
    <div className="ui-skeleton-container" role="status" aria-live="polite">
      {label && <p className="ui-skeleton-label">{label}</p>}

      {variant === 'cards' ? (
        <div className="cards ui-skeleton-cards-grid" aria-hidden="true">
          {items.map((idx) => (
            <div key={idx} className="card ui-skeleton-card">
              <div className="ui-skeleton-box ui-skeleton-title" />
              <div className="ui-skeleton-box ui-skeleton-row" />
              <div className="ui-skeleton-box ui-skeleton-row" />
            </div>
          ))}
        </div>
      ) : variant === 'slots' ? (
        <div className="time-slots-grid ui-skeleton-slots-grid" aria-hidden="true">
          {items.map((idx) => (
            <div key={idx} className="ui-skeleton-box ui-skeleton-slot" />
          ))}
        </div>
      ) : (
        <div className="ui-skeleton-rows" aria-hidden="true">
          {items.map((idx) => (
            <div key={idx} className="ui-skeleton-box ui-skeleton-table-row" />
          ))}
        </div>
      )}
    </div>
  )
}

/**
 * نمایش یکپارچه وضعیت خطا با پیام فارسی قابل فهم و دکمه «دوباره تلاش کن»
 */
export function ErrorState({
  message,
  onRetry,
  retryLabel = 'دوباره تلاش کن',
}) {
  const safeMessage = ensurePersianMessage(message)

  return (
    <div className="ui-error-state" role="alert">
      <p className="ui-error-message">{safeMessage}</p>
      {typeof onRetry === 'function' && (
        <button
          type="button"
          className="secondary-btn ui-retry-btn"
          onClick={onRetry}
        >
          {retryLabel}
        </button>
      )}
    </div>
  )
}

/**
 * نمایش یکپارچه وضعیت خالی (مثلاً «امروز نوبتی ندارید.»)
 */
export function EmptyState({ message = 'موردی برای نمایش وجود ندارد.', children }) {
  return (
    <div className="ui-empty-state" role="status">
      <p className="ui-empty-message">{toPersianDigits(message)}</p>
      {children && <div className="ui-empty-actions">{children}</div>}
    </div>
  )
}

/**
 * نمایش متن‌های بلند با کوتاه‌سازی خودکار (سه‌نقطه) تا چیدمان کارت یا جدول در هیچ صفحه‌ای به هم نریزد
 */
export function TruncatedText({
  text,
  as: Tag = 'span',
  lines = 1,
  className = '',
}) {
  const fullText = String(text ?? '')
  const clampClass = lines > 1 ? 'ui-truncate-multiline' : 'ui-truncate-single'

  return (
    <Tag
      className={`ui-truncated-text ${clampClass} ${className}`.trim()}
      title={fullText}
    >
      {fullText}
    </Tag>
  )
}

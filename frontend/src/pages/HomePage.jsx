import { useNavigate } from 'react-router-dom'
import { EmptyState, ErrorState, LoadingSkeleton, TruncatedText } from '../ui'
import { formatPrice, toPersianDigits } from '../utils/persian'

export default function HomePage({
  services,
  loading = false,
  error,
  onRetry,
  customer,
  onOpenBooking,
  onBack,
}) {
  const navigate = useNavigate()

  function handleBookingClick() {
    if (onOpenBooking) {
      onOpenBooking()
    } else {
      navigate('/booking')
    }
  }

  function handleBackClick() {
    if (onBack) {
      onBack()
    } else {
      navigate('/')
    }
  }

  return (
    <>
      <h1 className="title">خدمات ما</h1>

      {customer && (
        <p className="subtitle">
          خوش آمدید، <TruncatedText as="strong" text={customer.name} /> (
          {toPersianDigits(customer.phone)})
        </p>
      )}

      {loading ? (
        <LoadingSkeleton variant="cards" count={4} label="در حال بارگذاری خدمات آرایشگاه..." />
      ) : error ? (
        <ErrorState message={error} onRetry={onRetry} />
      ) : services.length === 0 ? (
        <EmptyState message="در حال حاضر هیچ خدمت فعالی برای نمایش وجود ندارد." />
      ) : (
        <div className="cards">
          {services.map((service) => (
            <div className="card" key={service.id}>
              <TruncatedText as="h2" className="card-name" lines={2} text={service.name} />
              <div className="card-row">
                <span>قیمت</span>
                <strong>{formatPrice(service.price)} تومان</strong>
              </div>
              <div className="card-row">
                <span>مدت زمان</span>
                <strong>{Number(service.duration).toLocaleString('fa-IR')} دقیقه</strong>
              </div>
            </div>
          ))}
        </div>
      )}

      <div className="booking-action">
        <button type="button" className="primary-btn" onClick={handleBookingClick}>
          نوبت گیری
        </button>
        <button type="button" className="secondary-btn" onClick={() => navigate('/track')}>
          پیگیری نوبت
        </button>
      </div>

      <div className="page-bottom-nav">
        <button type="button" className="secondary-btn" onClick={handleBackClick}>
          برگشت به صفحه اصلی
        </button>
      </div>
    </>
  )
}

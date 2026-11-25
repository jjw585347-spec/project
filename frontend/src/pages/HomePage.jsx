import { useNavigate } from 'react-router-dom'
import { formatPrice, toPersianDigits } from '../utils/persian'

export default function HomePage({ services, error, customer, onOpenBooking, onBack }) {
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
          خوش آمدید، <strong>{customer.name}</strong> ({toPersianDigits(customer.phone)})
        </p>
      )}

      {error && <p className="error">{error}</p>}

      {!error && services.length === 0 ? (
        <p className="owner-empty">در حال حاضر هیچ خدمت فعالی برای نمایش وجود ندارد.</p>
      ) : (
        <div className="cards">
          {services.map((service) => (
            <div className="card" key={service.id}>
              <h2 className="card-name">{service.name}</h2>
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
      </div>

      <div className="page-bottom-nav">
        <button type="button" className="secondary-btn" onClick={handleBackClick}>
          برگشت به صفحه اصلی
        </button>
      </div>
    </>
  )
}

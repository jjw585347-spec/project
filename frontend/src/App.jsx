import { useEffect, useState } from 'react'

// نمایش قیمت با جداکننده هزارگان و اعداد فارسی
function formatPrice(price) {
  return Number(price).toLocaleString('fa-IR')
}

function App() {
  const [services, setServices] = useState([])
  const [error, setError] = useState('')

  useEffect(() => {
    // گرفتن لیست خدمات از بک اند
    fetch('/api/services')
      .then((response) => response.json())
      .then((data) => setServices(data))
      .catch(() => setError('خطا در گرفتن لیست خدمات'))
  }, [])

  return (
    <div className="page" dir="rtl">
      <header className="header">
        <div className="logo">پیرایش عارف</div>
      </header>

      <main className="main">
        <h1 className="title">خدمات ما</h1>

        {error && <p className="error">{error}</p>}

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
      </main>
    </div>
  )
}

export default App

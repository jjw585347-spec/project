import { useEffect, useState } from 'react'

function App() {
  const [message, setMessage] = useState('در حال گرفتن پیام از بک اند...')

  useEffect(() => {
    // گرفتن پیام از آدرس تست بک اند
    fetch('/api/test')
      .then((response) => response.json())
      .then((data) => setMessage(data.message))
      .catch(() => setMessage('خطا: بک اند در دسترس نیست. آیا بک اند را اجرا کرده‌اید؟'))
  }, [])

  return (
    <div dir="rtl" style={{ textAlign: 'center', marginTop: '100px', fontSize: '24px' }}>
      <h1>{message}</h1>
    </div>
  )
}

export default App

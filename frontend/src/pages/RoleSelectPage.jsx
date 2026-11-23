import { useNavigate } from 'react-router-dom'

export default function RoleSelectPage({ onSelectCustomer, onSelectOwner }) {
  const navigate = useNavigate()

  function handleCustomerClick() {
    if (onSelectCustomer) {
      onSelectCustomer()
    } else {
      navigate('/login')
    }
  }

  function handleOwnerClick() {
    if (onSelectOwner) {
      onSelectOwner()
    } else {
      navigate('/owner')
    }
  }

  return (
    <div className="role-select-container">
      <h1 className="title">خوش آمدید</h1>
      <p className="subtitle">لطفاً نحوه ورود خود را انتخاب کنید</p>
      <div className="role-buttons">
        <button type="button" className="primary-btn role-btn" onClick={handleCustomerClick}>
          ورود به عنوان مشتری
        </button>
        <button type="button" className="secondary-btn role-btn" onClick={handleOwnerClick}>
          ورود به عنوان صاحب آرایشگاه
        </button>
      </div>
    </div>
  )
}

export default function RoleSelectPage({ onSelectCustomer, onSelectOwner }) {
  return (
    <div className="role-select-container">
      <h1 className="title">خوش آمدید</h1>
      <p className="subtitle">لطفاً نحوه ورود خود را انتخاب کنید</p>
      <div className="role-buttons">
        <button type="button" className="primary-btn role-btn" onClick={onSelectCustomer}>
          ورود به عنوان مشتری
        </button>
        <button type="button" className="secondary-btn role-btn" onClick={onSelectOwner}>
          ورود به عنوان صاحب آرایشگاه
        </button>
      </div>
    </div>
  )
}

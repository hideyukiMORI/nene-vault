import { useNavigate } from 'react-router-dom';
import { LoginForm } from '@/features/login';

export function LoginPage() {
  const navigate = useNavigate();
  return (
    <LoginForm
      onLoggedIn={() => {
        void navigate('/', { replace: true });
      }}
    />
  );
}

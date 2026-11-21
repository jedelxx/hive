import { Link } from "react-router-dom";
import AuthLayout from "../../components/AuthLayout";
import AuthInput from "../../components/AuthInput";

export default function Login() {
  function handleLogin(event) {
    event.preventDefault();
  }

  return (
    <AuthLayout
      title="Log in to Hive"
      subtitle="Welcome back! We're glad to see you."
      footer={
        <>
          Don't have an account?{" "}
          <Link
            to="/signup"
            className="font-bold text-[#8E5915] hover:text-[#E59312] hover:underline"
          >
            Sign up here
          </Link>
        </>
      }
    >
      <form onSubmit={handleLogin} className="flex flex-col gap-5">
        <AuthInput
          label="Username or Email"
          name="username"
          placeholder="Enter your username or email"
          autoComplete="username"
        />

        <AuthInput
          label="Password"
          name="password"
          type="password"
          placeholder="Enter your password"
          autoComplete="current-password"
        />

        <button
          type="submit"
          className="mt-2 flex h-12 w-full items-center justify-center gap-2 rounded-xl bg-[#E59312] px-5 font-bold text-[#1A141A] transition duration-200 hover:-translate-y-0.5 hover:bg-[#F4B315] hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-[#8E5915] focus:ring-offset-2"
        >
          Log in
          <span className="text-xl">→</span>
        </button>
      </form>
    </AuthLayout>
  );
}

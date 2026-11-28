import { useState } from "react";
import { Link } from "react-router-dom";
import AuthLayout from "../../components/AuthLayout";
import AuthInput from "../../components/AuthInput";

export default function Login() {
  const [errors, setErrors] = useState({});
  const [loginError, setLoginError] = useState("");

  function handleLogin(event) {
    event.preventDefault();

    const formData = new FormData(event.currentTarget);

    const username = String(formData.get("username") || "").trim();
    const password = String(formData.get("password") || "");

    const newErrors = {};

    if (!username) {
      newErrors.username = "Please enter your username or email.";
    }

    if (!password.trim()) {
      newErrors.password = "Please enter your password.";
    }

    setErrors(newErrors);
    setLoginError("");

    if (Object.keys(newErrors).length > 0) {
      return;
    }
  }

  return (
    <AuthLayout
      title="Log in to Hive"
      subtitle="Welcome back! Let's find your next hive."
      footer={
        <>
          Don't have an account?{" "}
          <Link
            to="/signup"
            className="font-bold text-[#8E5915] hover:text-[#E59312] hover:underline"
          >
            Create account here
          </Link>
        </>
      }
    >
      <form onSubmit={handleLogin} noValidate className="flex flex-col gap-5">
        <AuthInput
          label="Username or Email"
          name="username"
          placeholder="e.g., hive_user@gmail.com"
          autoComplete="username"
          error={errors.username}
        />

        <AuthInput
          label="Password"
          name="password"
          type="password"
          placeholder="Enter your password"
          autoComplete="current-password"
          error={errors.password || loginError}
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

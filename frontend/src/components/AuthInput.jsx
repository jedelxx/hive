import { useState } from "react";
import { Eye, EyeOff } from "lucide-react";

export default function AuthInput({
  label,
  name,
  type = "text",
  placeholder,
  autoComplete,
}) {
  const [showPassword, setShowPassword] = useState(false);

  const isPassword = type === "password";

  return (
    <div className="flex min-w-0 flex-col gap-1.5">
      <label htmlFor={name} className="text-sm font-semibold text-[#423738]">
        {label}
      </label>

      <div className="relative w-full">
        <input
          id={name}
          name={name}
          type={isPassword && showPassword ? "text" : type}
          placeholder={placeholder}
          autoComplete={autoComplete}
          required
          className={`h-11 w-full rounded-xl border border-[#D3AF85]/50 bg-[#FFFCF5] px-3.5 text-sm text-[#1A141A] outline-none transition placeholder:text-[#B5A69A] focus:border-[#E59312] focus:ring-2 focus:ring-[#E59312]/20 ${
            isPassword ? "pr-11" : ""
          }`}
        />

        {isPassword && (
          <button
            type="button"
            onClick={() => setShowPassword(!showPassword)}
            aria-label={showPassword ? "Hide password" : "Show password"}
            className="absolute right-3 top-1/2 -translate-y-1/2 text-[#8A7C76] transition hover:text-[#8E5915]"
          >
            {showPassword ? (
              <EyeOff size={20} strokeWidth={2} />
            ) : (
              <Eye size={20} strokeWidth={2} />
            )}
          </button>
        )}
      </div>
    </div>
  );
}

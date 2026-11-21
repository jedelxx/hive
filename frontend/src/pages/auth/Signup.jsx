import { useState } from "react";
import { Link } from "react-router-dom";
import AuthLayout from "../../components/AuthLayout";
import AuthInput from "../../components/AuthInput";

export default function Signup() {
  const [accountType, setAccountType] = useState("");
  const [idFile, setIdFile] = useState(null);

  function handleAccountTypeChange(event) {
    const selectedType = event.target.value;

    setAccountType(selectedType);

    // Clear uploaded ID when switching back to Seeker
    if (selectedType === "seeker") {
      setIdFile(null);
    }
  }

  function handleSignup(event) {
    event.preventDefault();

    // TODO: Connect to backend registration API
    // Keeper registration will also need to submit the uploaded ID file.
  }

  return (
    <AuthLayout
      title="Create a Hive Account"
      subtitle="Join our community. Your hive awaits!"
      showWelcome={false}
      footer={
        <>
          Already have an account?{" "}
          <Link
            to="/"
            className="font-bold text-[#8E5915] hover:text-[#E59312] hover:underline"
          >
            Log in here
          </Link>
        </>
      }
    >
      <form onSubmit={handleSignup} className="flex flex-col gap-4">
        {/* Account Type */}
        <div className="flex flex-col gap-1.5">
          <label
            htmlFor="account_type"
            className="text-sm font-semibold text-[#423738]"
          >
            Account Type
          </label>

          <select
            id="account_type"
            name="account_type"
            required
            value={accountType}
            onChange={handleAccountTypeChange}
            className="h-11 w-full rounded-xl border border-[#D3AF85]/50 bg-[#FFFCF5] px-3.5 text-sm text-[#423738] outline-none transition focus:border-[#E59312] focus:ring-2 focus:ring-[#E59312]/20"
          >
            <option value="" disabled>
              Select your account type
            </option>
            <option value="seeker">Seeker</option>
            <option value="keeper">Keeper</option>
          </select>
        </div>

        {/* First and Last Name */}
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <AuthInput
            label="First Name"
            name="first_name"
            placeholder="First name"
            autoComplete="given-name"
          />

          <AuthInput
            label="Last Name"
            name="last_name"
            placeholder="Last name"
            autoComplete="family-name"
          />
        </div>

        {/* Username */}
        <AuthInput
          label="Username"
          name="username"
          placeholder="Choose a username"
          autoComplete="username"
        />

        {/* Email */}
        <AuthInput
          label="Email Address"
          name="email"
          type="email"
          placeholder="Enter your email"
          autoComplete="email"
        />

        {/* Contact Number */}
        <AuthInput
          label="Contact Number"
          name="contact_no"
          type="tel"
          placeholder="Enter your contact number"
          autoComplete="tel"
        />

        {/* Sex */}
        <div className="flex flex-col gap-1.5">
          <label htmlFor="sex" className="text-sm font-semibold text-[#423738]">
            Sex
          </label>

          <select
            id="sex"
            name="sex"
            required
            defaultValue=""
            className="h-11 w-full rounded-xl border border-[#D3AF85]/50 bg-[#FFFCF5] px-3.5 text-sm text-[#423738] outline-none transition focus:border-[#E59312] focus:ring-2 focus:ring-[#E59312]/20"
          >
            <option value="" disabled>
              Select your sex
            </option>
            <option value="Male">Male</option>
            <option value="Female">Female</option>
          </select>
        </div>

        {/* Password */}
        <AuthInput
          label="Password"
          name="password"
          type="password"
          placeholder="Create a password"
          autoComplete="new-password"
        />

        {/* Keeper-only verification fields */}
        {accountType === "keeper" && (
          <div className="flex flex-col gap-4 rounded-xl border border-[#D3AF85]/50 bg-[#FFFCF5] p-4">
            <div>
              <h3 className="font-poppins text-base font-bold text-[#8E5915]">
                Keeper Verification
              </h3>

              <p className="font-manrope mt-1 text-xs leading-5 text-[#8A7C76]">
                Please provide a valid ID to verify your Keeper account.
              </p>
            </div>

            {/* Valid ID Type */}
            <div className="flex flex-col gap-1.5">
              <label
                htmlFor="valid_id_type"
                className="text-sm font-semibold text-[#423738]"
              >
                Valid ID Type
              </label>

              <select
                id="valid_id_type"
                name="valid_id_type"
                required
                defaultValue=""
                className="h-11 w-full rounded-xl border border-[#D3AF85]/50 bg-white px-3.5 text-sm text-[#423738] outline-none transition focus:border-[#E59312] focus:ring-2 focus:ring-[#E59312]/20"
              >
                <option value="" disabled>
                  Select your valid ID
                </option>
                <option value="National ID">National ID</option>
                <option value="Passport">Passport</option>
                <option value="Driver's License">Driver's License</option>
                <option value="UMID">UMID</option>
                <option value="PhilHealth ID">PhilHealth ID</option>
                <option value="SSS ID">SSS ID</option>
                <option value="Voter's ID">Voter's ID</option>
                <option value="Postal ID">Postal ID</option>
              </select>
            </div>

            {/* Upload Valid ID */}
            <div className="flex flex-col gap-1.5">
              <label
                htmlFor="valid_id_file"
                className="text-sm font-semibold text-[#423738]"
              >
                Upload Valid ID
              </label>

              <label
                htmlFor="valid_id_file"
                className="flex min-h-28 cursor-pointer flex-col items-center justify-center rounded-xl border-2 border-dashed border-[#D3AF85] bg-white px-4 py-5 text-center transition hover:border-[#E59312] hover:bg-[#FFF9E8]"
              >
                <span className="text-2xl text-[#E59312]">↑</span>

                <span className="mt-1 text-sm font-bold text-[#8E5915]">
                  Upload ID / Image / Document
                </span>

                <span className="font-manrope mt-1 text-xs text-[#8A7C76]">
                  PNG, JPG, JPEG, or PDF
                </span>

                {idFile && (
                  <span className="mt-3 max-w-full break-all text-xs font-semibold text-[#423738]">
                    Selected: {idFile.name}
                  </span>
                )}
              </label>

              <input
                id="valid_id_file"
                name="valid_id_file"
                type="file"
                accept="image/png,image/jpeg,image/jpg,application/pdf"
                required
                className="hidden"
                onChange={(event) => setIdFile(event.target.files?.[0] || null)}
              />

              <p className="font-manrope text-[11px] leading-5 text-[#8A7C76]">
                Make sure your ID is clear and readable.
              </p>
            </div>
          </div>
        )}

        {/* Submit Button */}
        <button
          type="submit"
          className="mt-2 flex h-12 w-full items-center justify-center gap-2 rounded-xl bg-[#E59312] px-5 font-bold text-[#1A141A] transition duration-200 hover:-translate-y-0.5 hover:bg-[#F4B315] hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-[#8E5915] focus:ring-offset-2"
        >
          Create Account
          <span className="text-xl">→</span>
        </button>
      </form>
    </AuthLayout>
  );
}

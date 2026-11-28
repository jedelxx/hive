import { useState } from "react";
import { Link } from "react-router-dom";
import AuthLayout from "../../components/AuthLayout";
import AuthInput from "../../components/AuthInput";

export default function Signup() {
  const [accountType, setAccountType] = useState("");
  const [idFile, setIdFile] = useState(null);
  const [errors, setErrors] = useState({});
  const [showSuccess, setShowSuccess] = useState(false);

  function handleAccountTypeChange(event) {
    const selectedType = event.target.value;

    setAccountType(selectedType);

    if (selectedType === "seeker") {
      setIdFile(null);
      setErrors((previousErrors) => ({
        ...previousErrors,
        valid_id_type: "",
        valid_id_file: "",
      }));
    }
  }

  function handleSignup(event) {
    event.preventDefault();

    const formData = new FormData(event.currentTarget);

    const getValue = (name) => String(formData.get(name) || "").trim();

    const firstName = getValue("first_name");
    const lastName = getValue("last_name");
    const username = getValue("username");
    const email = getValue("email");
    const contactNo = getValue("contact_no");
    const sex = getValue("sex");
    const password = String(formData.get("password") || "");
    const validIdType = getValue("valid_id_type");

    const newErrors = {};

    if (!accountType) {
      newErrors.account_type = "Please choose an account type.";
    }

    if (!firstName) {
      newErrors.first_name = "Please enter your first name.";
    }

    if (!lastName) {
      newErrors.last_name = "Please enter your last name.";
    }

    if (!username) {
      newErrors.username = "Please enter a username.";
    } else if (username.length < 3) {
      newErrors.username = "Username must be at least 3 characters.";
    }

    if (!email) {
      newErrors.email = "Please enter a valid email address.";
    } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
      newErrors.email = "Please enter a valid email address.";
    }

    if (!contactNo) {
      newErrors.contact_no = "Please enter your contact number.";
    } else if (!/^\+?[0-9\s()-]{10,16}$/.test(contactNo)) {
      newErrors.contact_no = "Please enter a valid contact number.";
    }

    if (!sex) {
      newErrors.sex = "Please choose your sex.";
    }

    if (!password.trim()) {
      newErrors.password = "Please enter a password.";
    } else if (password.length < 8) {
      newErrors.password = "Password must be at least 8 characters.";
    }

    if (accountType === "keeper") {
      if (!validIdType) {
        newErrors.valid_id_type = "Please select your valid ID type.";
      }

      if (!idFile) {
        newErrors.valid_id_file = "Please upload your valid ID.";
      } else {
        const allowedTypes = ["image/png", "image/jpeg", "application/pdf"];

        if (!allowedTypes.includes(idFile.type)) {
          newErrors.valid_id_file =
            "Only PNG, JPG, JPEG, and PDF files are allowed.";
        } else if (idFile.size > 5 * 1024 * 1024) {
          newErrors.valid_id_file = "File size must not exceed 5 MB.";
        }
      }
    }

    setErrors(newErrors);

    if (Object.keys(newErrors).length > 0) {
      return;
    }
    setShowSuccess(true);
  }

  const selectClass = (hasError) =>
    `h-11 w-full rounded-xl border bg-[#FFFCF5] px-3.5 text-sm text-[#423738] caret-[#F4B315] outline-none transition-all duration-200 ${
      hasError
        ? "border-red-500 ring-2 ring-red-500/20 ring-offset-1"
        : "border-[#D3AF85]/50 focus:border-[#F4B315] focus:ring-2 focus:ring-[#F4B315]/40 focus:ring-offset-1"
    }`;

  return (
    <>
      <AuthLayout
        title="Create a Hive Account"
        subtitle="Join our community. Your hive awaits!"
        isLogin={false}
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
        <form
          onSubmit={handleSignup}
          noValidate
          className="flex flex-col gap-4"
        >
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
              aria-invalid={Boolean(errors.account_type)}
              className={selectClass(errors.account_type)}
            >
              <option value="" disabled>
                Select your account type
              </option>
              <option value="seeker">Seeker (Find dorms and roommates)</option>
              <option value="keeper">
                Keeper (Post and manage dorm listings)
              </option>
            </select>

            {errors.account_type && (
              <p role="alert" className="text-xs text-red-600">
                {errors.account_type}
              </p>
            )}
          </div>

          {/* seeker and keeper fields */}
          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <AuthInput
              label="First Name"
              name="first_name"
              placeholder="First name"
              autoComplete="given-name"
              error={errors.first_name}
            />

            <AuthInput
              label="Last Name"
              name="last_name"
              placeholder="Last name"
              autoComplete="family-name"
              error={errors.last_name}
            />
          </div>

          <AuthInput
            label="Username"
            name="username"
            placeholder="Create a username"
            autoComplete="username"
            error={errors.username}
          />

          <AuthInput
            label="Email Address"
            name="email"
            type="email"
            placeholder="e.g., hive_user@gmail.com"
            autoComplete="email"
            error={errors.email}
          />

          <AuthInput
            label="Contact Number"
            name="contact_no"
            type="tel"
            placeholder="e.g., +639123456789"
            autoComplete="tel"
            error={errors.contact_no}
          />

          <div className="flex flex-col gap-1.5">
            <label
              htmlFor="sex"
              className="text-sm font-semibold text-[#423738]"
            >
              Sex
            </label>

            <select
              id="sex"
              name="sex"
              required
              defaultValue=""
              aria-invalid={Boolean(errors.sex)}
              className={selectClass(errors.sex)}
            >
              <option value="" disabled>
                Select your sex
              </option>
              <option value="Female">Female</option>
              <option value="Male">Male</option>
              <option value="Prefer not to say">Prefer not to say</option>
            </select>

            {errors.sex && (
              <p role="alert" className="text-xs text-red-600">
                {errors.sex}
              </p>
            )}
          </div>

          <AuthInput
            label="Password"
            name="password"
            type="password"
            placeholder="Create a password with at least 8 characters"
            autoComplete="new-password"
            error={errors.password}
          />

          {/* keeper-only verification fields */}
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

              {/* valid ID types */}
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
                  aria-invalid={Boolean(errors.valid_id_type)}
                  className={selectClass(errors.valid_id_type)}
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

                {errors.valid_id_type && (
                  <p role="alert" className="text-xs text-red-600">
                    {errors.valid_id_type}
                  </p>
                )}
              </div>

              {/* upload valid ID */}
              <div className="flex flex-col gap-1.5">
                <label
                  htmlFor="valid_id_file"
                  className="text-sm font-semibold text-[#423738]"
                >
                  Upload Valid ID
                </label>

                <label
                  htmlFor="valid_id_file"
                  className={`flex min-h-28 cursor-pointer flex-col items-center justify-center rounded-xl border-2 border-dashed bg-white px-4 py-5 text-center transition hover:border-[#E59312] hover:bg-[#FFF9E8] ${
                    errors.valid_id_file ? "border-red-500" : "border-[#D3AF85]"
                  }`}
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
                  onChange={(event) => {
                    setIdFile(event.target.files?.[0] || null);
                  }}
                />

                {errors.valid_id_file && (
                  <p role="alert" className="text-xs text-red-600">
                    {errors.valid_id_file}
                  </p>
                )}

                <p className="font-manrope text-[11px] leading-5 text-[#8A7C76]">
                  Ensure your ID is clear and readable.
                </p>
              </div>
            </div>
          )}

          <button
            type="submit"
            className="mt-2 flex h-12 w-full items-center justify-center gap-2 rounded-xl bg-[#E59312] px-5 font-bold text-[#1A141A] transition duration-200 hover:-translate-y-0.5 hover:bg-[#F4B315] hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-[#8E5915] focus:ring-offset-2"
          >
            Create Account
            <span className="text-xl">→</span>
          </button>
        </form>
      </AuthLayout>

      {/* once account is created */}
      {showSuccess && (
        <div
          className="fixed inset-0 z-100 flex items-center justify-center bg-[#1A141A]/60 px-5 py-8"
          role="dialog"
          aria-modal="true"
          aria-labelledby="success-title"
        >
          <div className="w-full max-w-md rounded-2xl border border-[#D3AF85]/50 bg-[#FFF9E8] p-7 text-center shadow-2xl sm:p-9">
            <div className="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-[#F4B315]/20">
              <span className="text-4xl font-bold text-[#8E5915]">✓</span>
            </div>

            <h2
              id="success-title"
              className="font-poppins mt-5 text-xl font-extrabold text-[#1A141A] sm:text-2xl"
            >
              Account successfully created!
            </h2>

            <p className="font-manrope mt-3 text-sm text-[#423738]">
              You may now log in.
            </p>

            <Link
              to="/"
              className="mt-6 inline-flex h-11 items-center justify-center rounded-xl bg-[#E59312] px-5 font-bold text-[#1A141A] transition hover:bg-[#F4B315] hover:shadow-lg"
            >
              Log in here
            </Link>
          </div>
        </div>
      )}
    </>
  );
}

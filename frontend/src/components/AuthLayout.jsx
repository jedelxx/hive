import bee from "../assets/bee.svg";
import beehive from "../assets/beehive.svg";
import honeyDrips from "../assets/honeydrip.svg";
import honeycombBg from "../assets/honeycomb-bg.jpg";

export default function AuthLayout({
  title,
  subtitle,
  children,
  footer,
  showWelcome = true,
}) {
  return (
    <div className="relative flex min-h-svh flex-col overflow-x-hidden bg-[#FFF9E8] font-manrope text-[#1A141A]">
      {/* Honeycomb background - visible on BOTH pages */}
      <div
        aria-hidden="true"
        className="pointer-events-none absolute inset-0 z-0"
        style={{
          backgroundImage: `url("${honeycombBg}")`,
          backgroundRepeat: "repeat-y",
          backgroundSize: "100% auto",
          backgroundPosition: "top center",
          opacity: 0.18,
        }}
      />

      {/* Top honey decoration - login only */}
      {showWelcome && (
        <img
          src={honeyDrips}
          alt=""
          aria-hidden="true"
          className="pointer-events-none absolute left-0 top-0 z-20 h-auto w-full"
        />
      )}

      {/* Main content */}
      <main
        className={`relative z-10 mx-auto flex w-full flex-1 items-center justify-center px-5 md:px-10 ${
          showWelcome
            ? "max-w-6xl grid grid-cols-1 gap-8 pb-10 pt-36 lg:grid-cols-[1fr_440px] lg:gap-16 lg:pb-12 lg:pt-40"
            : "max-w-none py-10"
        }`}
      >
        {/* Login welcome section */}
        {showWelcome && (
          <section className="mx-auto flex w-full max-w-lg flex-col items-center text-center lg:items-start lg:text-left">
            <p className="font-manrope text-xs font-extrabold tracking-[0.3em] text-[#8E5915]">
              WELCOME TO THE HIVE
            </p>

            <h1 className="font-poppins mt-4 text-4xl font-extrabold leading-tight text-[#1A141A] sm:text-5xl lg:text-6xl">
              Where every
              <span className="block text-[#E59312]">bee belongs.</span>
            </h1>

            <p className="font-manrope mt-5 max-w-md text-sm leading-7 text-[#423738] sm:text-base">
              Connect, discover opportunities, and become part of a growing
              community of seekers and keepers.
            </p>

            <img
              src={beehive}
              alt="Beehive illustration"
              className="h-32 w-32 animate-[float_4s_ease-in-out_infinite] object-contain"
            />

            <p className="font-manrope hidden text-sm italic text-[#8E5915] lg:block">
              Sweet connections start here.
            </p>
          </section>
        )}

        {/* Authentication card and flying bees */}
        <section
          className={`relative mx-auto w-full ${
            showWelcome ? "max-w-110" : "max-w-140"
          }`}
        >
          {/* Flying bees */}
          <div
            aria-hidden="true"
            className="pointer-events-none absolute inset-0 z-30 hidden sm:block"
          >
            <img
              src={bee}
              alt=""
              className="absolute -top-9 left-[12%] h-7 w-7 animate-[beeFlying1_5s_ease-in-out_infinite]"
            />

            <img
              src={bee}
              alt=""
              className="absolute -top-5 right-[15%] h-7 w-7 animate-[beeFlying2_6s_ease-in-out_infinite]"
            />

            <img
              src={bee}
              alt=""
              className="absolute -right-9 top-[35%] h-7 w-7 animate-[beeFlying3_5.5s_ease-in-out_infinite]"
            />

            <img
              src={bee}
              alt=""
              className="absolute -left-9 top-[60%] h-7 w-7 animate-[beeFlying4_6s_ease-in-out_infinite]"
            />

            <img
              src={bee}
              alt=""
              className="absolute -bottom-8 right-[25%] h-7 w-7 animate-[beeFlying5_7s_ease-in-out_infinite]"
            />

            {!showWelcome && (
              <>
                <img
                  src={bee}
                  alt=""
                  className="absolute -top-12 right-[42%] h-6 w-6 animate-[beeFlying2_5.5s_ease-in-out_infinite]"
                />

                <img
                  src={bee}
                  alt=""
                  className="absolute -left-12 top-[20%] h-6 w-6 animate-[beeFlying3_6s_ease-in-out_infinite]"
                />

                <img
                  src={bee}
                  alt=""
                  className="absolute -right-12 bottom-[15%] h-6 w-6 animate-[beeFlying1_6.5s_ease-in-out_infinite]"
                />
              </>
            )}
          </div>

          {/* Authentication card */}
          <div className="relative z-10 overflow-hidden rounded-[25px] border border-[#8E5915]/20 bg-white shadow-[0_15px_45px_rgba(66,55,56,0.15)]">
            {/* Honey dripping over card */}
            <img
              src={honeyDrips}
              alt=""
              aria-hidden="true"
              className="pointer-events-none absolute left-0 top-0 z-20 h-17 w-full object-cover object-top"
            />

            <div
              className={`relative z-10 px-5 pb-7 sm:px-8 ${
                showWelcome ? "pt-11" : "pt-12"
              }`}
            >
              <div className="mb-7 text-center">
                <h2 className="font-poppins text-2xl font-extrabold text-[#1A141A] sm:text-3xl">
                  {title}
                </h2>

                <p className="font-manrope mt-2 text-xs text-[#8A7C76] sm:text-sm">
                  {subtitle}
                </p>
              </div>

              {children}

              <div className="font-manrope mt-6 text-center text-xs text-[#423738] sm:text-sm">
                {footer}
              </div>
            </div>
          </div>
        </section>
      </main>

      {/* Footer - bottom left */}
      <footer className="relative z-10 flex w-full flex-col items-start px-5 pb-5 pt-2 lg:px-10 lg:pb-7">
        <div className="flex items-center gap-2">
          <img src={bee} alt="" className="h-8 w-8" />

          <span className="font-poppins text-xl font-black tracking-[0.3em] text-[#8E5915]">
            HIVE
          </span>
        </div>

        <p className="font-manrope ml-10 mt-1 text-[11px] text-[#423738]">
          Find your place in the hive.
        </p>
      </footer>
    </div>
  );
}

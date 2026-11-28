import bee from "../assets/bee.svg";
import beehive from "../assets/beehive.svg";
import honeyDrips from "../assets/honeydrip.svg";
import honeycombBg from "../assets/honeycomb-bg.jpg";
import buildings from "../assets/buildings.svg";

export default function AuthLayout({
  title,
  subtitle,
  children,
  footer,
  isLogin = true,
}) {
  return (
    <div className="relative flex min-h-svh flex-col overflow-x-hidden bg-[#FFF9E8] font-manrope text-[#1A141A]">
      {/* yellow honeycomb bg */}
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

      {/* honey dripping from top of screen (login/welcome page only) */}
      {isLogin && (
        <img
          src={honeyDrips}
          alt=""
          aria-hidden="true"
          className="pointer-events-none absolute left-0 top-0 z-0 h-auto w-full max-h-32 object-fill sm:max-h-36 md:max-h-40 lg:max-h-44 xl:max-h-none"
        />
      )}

      {/* main content */}
      <main
        className={`relative z-10 mx-auto flex w-full flex-1 items-center px-4 pt-14 sm:px-6 sm:pt-16 md:px-10 ${
          isLogin
            ? "max-w-6xl grid-cols-1 grid items-center gap-6 pb-4 sm:pb-6 lg:grid-cols-[minmax(0,1fr)_minmax(380px,440px)] lg:gap-8 lg:pb-2 lg:pt-12 xl:gap-12"
            : "max-w-none justify-center pb-12 pt-12 sm:pb-16 sm:pt-16"
        }`}
      >
        {/* login/welcome section */}
        {isLogin && (
          <section className="relative mx-auto flex w-full max-w-lg translate-y-2 flex-col items-center text-center lg:translate-y-5 lg:items-start lg:text-left">
            <p className="font-manrope text-s font-extrabold tracking-[0.4em] text-[#8E5915]">
              WELCOME TO HIVE
            </p>

            <h1 className="font-poppins mt-3 text-3xl font-extrabold leading-tight text-[#1A141A] sm:text-4xl lg:text-4xl xl:text-5xl">
              Find dormitories
              <span className="block text-[#E59312]">near PUP Main.</span>
            </h1>

            <p className="font-manrope mt-4 max-w-md text-sm leading-7 text-[#423738] sm:text-base">
              Discover potential roommates with similar preferences and connect
              with landlords using our secure chat system.
            </p>

            {/* buildings illustrations */}
            <div className="flex h-28 w-full items-end justify-center gap-1 sm:h-32 lg:h-36">
              {Array.from({ length: 4 }).map((_, index) => (
                <img
                  key={index}
                  src={buildings}
                  alt=""
                  aria-hidden="true"
                  className="h-full w-1/4 object-contain opacity-50 object-bottom"
                />
              ))}
            </div>

            {/* HIVE features summary */}
            <div className="relative z-10 -mt-1 flex flex-wrap items-center justify-center gap-x-5 gap-y-2 lg:justify-start">
              <div className="flex items-center gap-2">
                <span className="flex h-4 w-4 items-center justify-center rounded-full border border-[#E59312] text-[10px] font-bold text-[#E59312]">
                  ✓
                </span>
                <span className="font-manrope text-xs text-[#423738]">
                  Completely free
                </span>
              </div>

              <div className="flex items-center gap-2">
                <span className="flex h-4 w-4 items-center justify-center rounded-full border border-[#E59312] text-[10px] font-bold text-[#E59312]">
                  ✓
                </span>
                <span className="font-manrope text-xs text-[#423738]">
                  Secure in-app messaging
                </span>
              </div>

              <div className="flex items-center gap-2">
                <span className="flex h-4 w-4 items-center justify-center rounded-full border border-[#E59312] text-[10px] font-bold text-[#E59312]">
                  ✓
                </span>
                <span className="font-manrope text-xs text-[#423738]">
                  Legitimate listing
                </span>
              </div>
            </div>
          </section>
        )}

        {/* authentication card and flying bees */}
        <section
          className={`relative mx-auto w-full ${
            isLogin ? "max-w-110" : "max-w-140"
          }`}
        >
          {/* flying bees */}
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
              className="absolute -bottom-5 right-[40%] h-7 w-7 animate-[beeFlying5_7s_ease-in-out_infinite]"
            />

            {!isLogin && (
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

          {/* authentication card */}
          <div className="relative z-10 overflow-hidden rounded-[25px] border border-[#8E5915]/20 bg-white shadow-[0_15px_45px_rgba(66,55,56,0.15)]">
            {/* honey dripping on the topmost of auth card */}
            <img
              src={honeyDrips}
              alt=""
              aria-hidden="true"
              className="pointer-events-none absolute left-0 top-0 z-20 h-17 w-full object-cover object-top"
            />

            <div
              className={`relative z-10 px-5 pb-7 sm:px-8 ${
                isLogin ? "pt-11" : "pt-13 sm:pt-18"
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

          {/* floating beehive - login page only */}
          {isLogin && (
            <img
              src={beehive}
              alt="Beehive illustration"
              className="pointer-events-none absolute left-1/2 top-full -mt-1 h-12 w-12 -translate-x-1/2 animate-[hiveSway_4s_ease-in-out_infinite] object-contain sm:h-12 sm:w-12 lg:h-14 lg:w-14 xl:w-25 xl:h-25"
            />
          )}
        </section>
      </main>

      {/* footer logo and slogan */}
      <footer className="relative z-10 flex w-full flex-col items-start px-5 pb-3 pt-1 sm:pb-4 lg:px-10">
        <div className="flex items-center gap-2">
          <img src={bee} alt="" className="h-8 w-8" />

          <span className="font-berkshire text-2xl font-black tracking-wide text-[#8E5915]">
            HIVE
          </span>
        </div>

        <p className="font-manrope ml-10 mt-1 text-sm italic text-[#70420F]">
          Find the place where you beelong.
        </p>
      </footer>
    </div>
  );
}

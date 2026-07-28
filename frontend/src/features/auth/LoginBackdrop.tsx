/**
 * CSS approximation of the legacy login page's photographic background
 * (ship porthole / wheel machinery). Swap for the real background image
 * asset when available — see LoginPage.
 */
export function LoginBackdrop() {
  return (
    <div
      aria-hidden="true"
      className="absolute inset-0 overflow-hidden bg-gradient-to-br from-gray-400 via-gray-600 to-gray-900"
    >
      <div className="absolute right-[-10%] top-[-25%] h-[140%] w-[85%] rounded-full border-[40px] border-gray-700/60" />
      <div className="absolute right-[-5%] top-[-15%] h-[120%] w-[70%] rounded-full border-[28px] border-gray-800/70" />
      <div className="absolute bottom-[-30%] right-[5%] h-[65%] w-[45%] rounded-full bg-black/80" />
      <div className="absolute inset-0 bg-black/10" />
    </div>
  );
}

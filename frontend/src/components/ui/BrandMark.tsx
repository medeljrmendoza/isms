/** Placeholder brand mark — swap for the real logo asset when available. */
export function BrandMark({ className = "" }: { className?: string }) {
  return (
    <svg viewBox="0 0 120 80" className={className} role="img" aria-label="Company logo">
      <ellipse cx="60" cy="40" rx="58" ry="38" fill="#38b6e8" />
      <g stroke="#ffffff" strokeWidth="4" strokeLinecap="round">
        <line x1="30" y1="56" x2="78" y2="20" />
        <line x1="38" y1="58" x2="84" y2="26" />
        <line x1="46" y1="60" x2="90" y2="32" />
        <line x1="54" y1="61" x2="95" y2="39" />
      </g>
    </svg>
  );
}

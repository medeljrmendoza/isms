import type { SVGProps } from "react";

export function EyeIcon(props: SVGProps<SVGSVGElement>) {
  return (
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={1.8} {...props}>
      <path d="M2.25 12s3.75-7 9.75-7 9.75 7 9.75 7-3.75 7-9.75 7-9.75-7-9.75-7Z" strokeLinecap="round" strokeLinejoin="round" />
      <circle cx="12" cy="12" r="3" strokeLinecap="round" strokeLinejoin="round" />
    </svg>
  );
}

export function EyeOffIcon(props: SVGProps<SVGSVGElement>) {
  return (
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={1.8} {...props}>
      <path
        d="M3 3l18 18M10.58 10.58a3 3 0 0 0 4.24 4.24M9.88 5.09A9.77 9.77 0 0 1 12 5c6 0 9.75 7 9.75 7a17.6 17.6 0 0 1-3.23 4.13M6.62 6.62C4.24 8.14 2.25 10.5 2.25 10.5s.28.51.83 1.25"
        strokeLinecap="round"
        strokeLinejoin="round"
      />
    </svg>
  );
}

export function ArrowRightCircleIcon(props: SVGProps<SVGSVGElement>) {
  return (
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={1.8} {...props}>
      <circle cx="12" cy="12" r="9.25" strokeLinecap="round" strokeLinejoin="round" />
      <path d="M10 9l3 3-3 3" strokeLinecap="round" strokeLinejoin="round" />
    </svg>
  );
}

export function QuestionMarkCircleIcon(props: SVGProps<SVGSVGElement>) {
  return (
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={1.8} {...props}>
      <circle cx="12" cy="12" r="9.25" strokeLinecap="round" strokeLinejoin="round" />
      <path d="M9.5 9.5a2.5 2.5 0 1 1 3.4 2.33c-.7.27-1.15.9-1.15 1.67v.25" strokeLinecap="round" strokeLinejoin="round" />
      <circle cx="12" cy="17" r="0.75" fill="currentColor" stroke="none" />
    </svg>
  );
}

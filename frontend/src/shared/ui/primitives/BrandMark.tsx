/**
 * BrandMark — the NeNe Vault seal (二重円の印影 × 鍵穴 / double-ring impression
 * with a keyhole). The mark inherits its color from `currentColor`, so callers
 * set the seal color with a text-color utility (`text-seal` on light surfaces,
 * `text-seal-bright` on dark). Per brand spec the mark is vermilion (朱) only.
 *
 * In:  size (px), simplified (drop the inner ring for ≤20px / favicon sizes),
 *      title (accessible name; omit for decorative use), className
 * Out: nothing — purely presentational SVG.
 * Does not: fetch data, read router/query cache, or hard-code its color.
 */
export interface BrandMarkProps {
  size?: number;
  simplified?: boolean;
  title?: string;
  className?: string;
}

export function BrandMark({ size = 34, simplified = false, title, className }: BrandMarkProps) {
  const labelled = title !== undefined && title !== '';
  return (
    <svg
      className={className}
      width={size}
      height={size}
      viewBox="0 0 34 34"
      fill="none"
      role={labelled ? 'img' : 'presentation'}
      aria-label={labelled ? title : undefined}
      aria-hidden={labelled ? undefined : true}
    >
      {simplified ? (
        <>
          <circle cx="17" cy="17" r="14.6" fill="none" stroke="currentColor" strokeWidth="2.4" />
          <circle cx="17" cy="14.2" r="3.4" fill="currentColor" />
          <path d="M15.2 15.8 13.8 22.6h6.4l-1.4-6.8Z" fill="currentColor" />
        </>
      ) : (
        <>
          <circle cx="17" cy="17" r="14.4" fill="none" stroke="currentColor" strokeWidth="1.9" />
          <circle cx="17" cy="17" r="11" fill="none" stroke="currentColor" strokeWidth="1.05" />
          <circle cx="17" cy="14.2" r="3.15" fill="currentColor" />
          <path d="M15.3 15.9 14 22.4h6l-1.3-6.5Z" fill="currentColor" />
        </>
      )}
    </svg>
  );
}

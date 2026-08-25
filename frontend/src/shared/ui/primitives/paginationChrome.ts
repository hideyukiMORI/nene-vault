// The page-level pagination row on top of the kit's `Pagination` (0.17.0): the rule above
// it, the muted 12px type with an inherited line-height (判例40), the range on the left with
// the buttons pushed right; below `md` the range takes its own line and the two buttons
// share the next one side by side (owner NG 2026-08-26 on the kit's fully stacked column).
export const PAGINATION_CHROME =
  'px-4 py-3 border-t border-border text-xs leading-inherit text-text-muted ' +
  '[&>div>span]:mr-auto ' +
  'max-md:[&>div]:flex-wrap max-md:[&>div>span]:basis-full max-md:[&>div>span]:mr-0 ' +
  'max-md:[&>div>span]:text-center max-md:[&>div>button]:flex-1';

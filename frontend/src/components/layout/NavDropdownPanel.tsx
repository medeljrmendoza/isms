import { Link } from "react-router-dom";
import type { NavChild } from "../../types/navigation";
import { isNavGroup } from "../../types/navigation";

/**
 * Renders one open top-level dropdown. Groups (e.g. "Risk Assessment"
 * under "Operations") show as a row with an arrow and flyout their
 * children to the side on hover, matching the legacy Bootstrap
 * dropdown-submenu behavior.
 *
 * Deliberately no overflow/height cap here — an ancestor with
 * overflow set would clip the flyout (`left-full`) the same way it
 * clipped the whole panel before, so this just lets the page scroll
 * for the longer menus (e.g. Setup) instead.
 */
export function NavDropdownPanel({
  children,
  activePath,
  onNavigate,
}: {
  children: NavChild[];
  activePath: string;
  onNavigate: () => void;
}) {
  return (
    <div className="absolute left-0 top-full z-20 w-72 rounded-md border border-slate-200 bg-white py-2 shadow-xl">
      {children.map((child) =>
        isNavGroup(child) ? (
          <div key={child.label} className="group/submenu relative">
            <button
              type="button"
              className={`flex w-full items-center justify-between gap-2 px-4 py-1.5 text-left text-sm hover:bg-slate-100 group-hover/submenu:bg-slate-100 ${
                child.children.some((leaf) => leaf.path === activePath) ? "font-semibold text-sky-700" : "text-slate-700"
              }`}
            >
              <span className="flex items-center gap-2">
                {child.children.some((leaf) => leaf.path === activePath) && (
                  <span className="h-1.5 w-1.5 shrink-0 rounded-full bg-sky-500" aria-hidden="true" />
                )}
                {child.label}
              </span>
              <span className="text-slate-400">▸</span>
            </button>
            <div className="absolute left-full top-0 z-30 hidden w-72 rounded-md border border-slate-200 bg-white py-2 shadow-xl group-hover/submenu:block">
              {child.children.map((leaf) => (
                <Link
                  key={leaf.path}
                  to={leaf.path}
                  onClick={onNavigate}
                  className={`flex items-center gap-2 px-4 py-1.5 text-sm hover:bg-slate-100 ${
                    leaf.path === activePath ? "bg-sky-50 font-semibold text-sky-700" : "text-slate-700"
                  }`}
                >
                  {leaf.path === activePath && <span className="h-1.5 w-1.5 shrink-0 rounded-full bg-sky-500" aria-hidden="true" />}
                  {leaf.label}
                </Link>
              ))}
            </div>
          </div>
        ) : (
          <Link
            key={child.path}
            to={child.path}
            onClick={onNavigate}
            className={`flex items-center gap-2 px-4 py-1.5 text-sm hover:bg-slate-100 ${
              child.path === activePath ? "bg-sky-50 font-semibold text-sky-700" : "text-slate-700"
            }`}
          >
            {child.path === activePath && <span className="h-1.5 w-1.5 shrink-0 rounded-full bg-sky-500" aria-hidden="true" />}
            {child.label}
          </Link>
        ),
      )}
    </div>
  );
}

import { useEffect, useRef, useState } from "react";
import { Link, useLocation, useNavigate } from "react-router-dom";
import { useAuth } from "../../context/AuthContext";
import { navigation } from "../../data/navigation";
import { NavDropdownPanel } from "./NavDropdownPanel";
import { GuideModal } from "./GuideModal";
import { isNavGroup, type NavChild } from "../../types/navigation";

const ENV_LABEL = import.meta.env.VITE_ENV_LABEL;

/** Whether the current route is one of this dropdown's (possibly nested) leaves. */
function containsActivePath(children: NavChild[], pathname: string): boolean {
  return children.some((child) => (isNavGroup(child) ? containsActivePath(child.children, pathname) : child.path === pathname));
}

export function NavBar() {
  const { user, logout } = useAuth();
  const navigate = useNavigate();
  const location = useLocation();
  const [openMenu, setOpenMenu] = useState<string | null>(null);
  const [showGuide, setShowGuide] = useState(false);
  const navRef = useRef<HTMLElement>(null);

  useEffect(() => {
    setOpenMenu(null);
  }, [location.pathname]);

  useEffect(() => {
    function handleClickOutside(event: MouseEvent) {
      if (navRef.current && !navRef.current.contains(event.target as Node)) {
        setOpenMenu(null);
      }
    }
    document.addEventListener("mousedown", handleClickOutside);
    return () => document.removeEventListener("mousedown", handleClickOutside);
  }, []);

  const handleLogout = async () => {
    await logout();
    navigate("/login", { replace: true });
  };

  return (
    <nav ref={navRef} className="sticky top-0 z-30 border-b border-gray-900 bg-gray-800 px-4 py-2.5">
      <div className="flex flex-wrap items-center gap-1">
        <div className="mr-3 flex items-center gap-2">
          <span className="text-sm font-bold uppercase tracking-widest text-white">ISMS</span>
          {ENV_LABEL && (
            <span className="rounded bg-amber-500/90 px-1.5 py-0.5 text-[10px] font-bold uppercase text-white">
              {ENV_LABEL}
            </span>
          )}
        </div>

        {navigation.map((item) => {
          const isActive = item.children
            ? containsActivePath(item.children, location.pathname)
            : item.path === location.pathname;

          return item.children ? (
            <div key={item.label} className="relative">
              <button
                type="button"
                onClick={() => setOpenMenu((prev) => (prev === item.label ? null : item.label))}
                className={`flex items-center gap-1 rounded px-3 py-1.5 text-sm font-medium text-slate-200 hover:bg-white/10 hover:text-white ${
                  openMenu === item.label ? "bg-white/10 text-white" : ""
                } ${isActive ? "text-white" : ""}`}
              >
                {isActive && <span className="h-1.5 w-1.5 rounded-full bg-sky-400" aria-hidden="true" />}
                {item.label} <span className="ml-0.5 text-[10px]">▾</span>
              </button>
              {openMenu === item.label && (
                <NavDropdownPanel children={item.children} activePath={location.pathname} onNavigate={() => setOpenMenu(null)} />
              )}
            </div>
          ) : (
            <Link
              key={item.label}
              to={item.path!}
              className={`flex items-center gap-1 rounded px-3 py-1.5 text-sm font-medium hover:bg-white/10 hover:text-white ${
                isActive ? "text-white" : "text-slate-200"
              }`}
            >
              {isActive && <span className="h-1.5 w-1.5 rounded-full bg-sky-400" aria-hidden="true" />}
              {item.label}
            </Link>
          );
        })}

        <div className="ml-auto flex items-center gap-2">
          <button
            type="button"
            onClick={() => setShowGuide(true)}
            className="rounded px-2 py-1.5 text-xs font-medium text-slate-300 hover:bg-white/10 hover:text-white"
          >
            ? Guide
          </button>

          <div className="relative">
            <button
              type="button"
              onClick={() => setOpenMenu((prev) => (prev === "__user" ? null : "__user"))}
              className={`flex items-center gap-1.5 rounded px-3 py-1.5 text-sm font-medium text-slate-200 hover:bg-white/10 hover:text-white ${
                openMenu === "__user" ? "bg-white/10 text-white" : ""
              }`}
            >
              {user?.name?.toUpperCase()} <span className="text-[10px]">▾</span>
            </button>
            {openMenu === "__user" && (
              <div className="absolute right-0 top-full z-20 w-56 rounded-md border border-slate-200 bg-white py-2 shadow-xl">
                <Link
                  to="/settings"
                  onClick={() => setOpenMenu(null)}
                  className="block px-4 py-1.5 text-sm text-slate-700 hover:bg-slate-100"
                >
                  Account Settings
                </Link>
                <button
                  type="button"
                  onClick={handleLogout}
                  className="block w-full px-4 py-1.5 text-left text-sm text-slate-700 hover:bg-slate-100"
                >
                  Log Out
                </button>
              </div>
            )}
          </div>
        </div>
      </div>

      {showGuide && <GuideModal onClose={() => setShowGuide(false)} />}
    </nav>
  );
}

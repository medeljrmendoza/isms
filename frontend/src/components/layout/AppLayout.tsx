import { Outlet } from "react-router-dom";
import { NavBar } from "../dashboard/NavBar";
import { NotificationBar } from "../dashboard/NotificationBar";

export function AppLayout() {
  return (
    <div className="min-h-screen bg-slate-50">
      <NavBar />
      <NotificationBar />
      <main className="mx-auto max-w-6xl px-4 py-6">
        <Outlet />
      </main>
    </div>
  );
}

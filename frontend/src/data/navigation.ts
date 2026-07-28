import type { NavItem } from "../types/navigation";

/**
 * Transcribed from Views/admin/components/nav_bar.php. Every path below
 * matches a real legacy route (see Config/Routes.php); none of the target
 * pages are migrated yet, so they all resolve to the "coming soon"
 * placeholder until their module gets its own pass.
 *
 * Permission gating (`module_pages`, `setup_mod_*`, `user_level` checks in
 * the legacy file) is intentionally not reproduced yet — same deferral as
 * the Dashboard's `dashboard_pages` gating. Every item is shown to every
 * logged-in user until the roles/permissions module is migrated.
 *
 * A couple of legacy links carried dynamic date/id segments
 * (Vessel Tracker, Setup → Vessels) — simplified to static paths here
 * since the exact deep-link params don't matter for a placeholder target.
 */
export const navigation: NavItem[] = [
  { label: "Dashboard", path: "/dashboard" },
  { label: "Vessel Tracker", path: "/vessel_tracker" },
  {
    label: "Operations",
    children: [
      { label: "Nonconformities", path: "/nonconformities" },
      { label: "Incident Report / HOR", path: "/incident" },
      { label: "PSC Inspections", path: "/psc" },
      { label: "Company Inspections", path: "/company" },
      { label: "Internal Audits", path: "/internal" },
      { label: "External Audits", path: "/external" },
      {
        label: "Risk Assessment",
        children: [
          { label: "Vessel", path: "/risk_assessment" },
          { label: "Shore", path: "/risk_assessment_shore" },
        ],
      },
      { label: "SIRE", path: "/sire" },
      { label: "Non-SIRE", path: "/non_sire" },
      { label: "Flag State", path: "/flag_state" },
      { label: "Committee Meeting", path: "/committee_meeting" },
      { label: "Drill Reports", path: "/drill/calendar" },
      { label: "Exposure Hours", path: "/exposure_hours" },
      {
        label: "KPI",
        children: [
          { label: "Risk Assessment", path: "/report/risk_assessment" },
          { label: "Incident Report / HOR", path: "/report/incident" },
          { label: "PSC Inspections", path: "/kpi_psc_inspections" },
          { label: "Flag State", path: "/kpi_flag_state" },
          { label: "SIRE", path: "/kpi_sire" },
          { label: "Non-SIRE", path: "/kpi_non_sire" },
          { label: "Company Inspections", path: "/kpi_company_inspections" },
          { label: "SIRE vs Company Inspection", path: "/kpi_sire_vs_company_inspection" },
          { label: "Internal Audits", path: "/kpi_internal" },
          { label: "Claims", path: "/kpi_claims" },
        ],
      },
    ],
  },
  {
    label: "Safety Manual",
    children: [
      { label: "Manuals", path: "/sms" },
      { label: "Revision History", path: "/sms_revision" },
      { label: "Master Review", path: "/master_review" },
      { label: "ISPS Review", path: "/isps_review" },
    ],
  },
  {
    label: "Documentation",
    children: [
      { label: "Vessel Documentation", path: "/vessel_documentation" },
      { label: "Company Documentation", path: "/company_documentation" },
    ],
  },
  {
    label: "PMS",
    children: [
      { label: "Running Hours", path: "/pms_running_hours_equipments" },
      {
        label: "Maintenance",
        children: [
          { label: "Planned Maintenance", path: "/pms_activities" },
          { label: "Unplanned Maintenance", path: "/pms_work_plan" },
        ],
      },
      { label: "Defect List", path: "/defect_list" },
      {
        label: "Reports",
        children: [
          { label: "Done Activities", path: "/pms_done_activities" },
          { label: "Overdue / Upcoming Activities", path: "/pms_upcoming_activities" },
          { label: "Annual Performance Report", path: "/pms_annual_performance" },
          { label: "Performance Index", path: "/pms_performance_index" },
          { label: "Tickets", path: "/pms_tickets" },
        ],
      },
    ],
  },
  { label: "Claims", path: "/jpi" },
  {
    label: "Address Book",
    children: [
      { label: "Contact List", path: "/address_book_contacts" },
      { label: "Follow-up History", path: "/address_book_history" },
    ],
  },
  {
    label: "Tasks",
    children: [
      { label: "Task Summary", path: "/task_summary" },
      { label: "User Tasks", path: "/user_task" },
    ],
  },
  {
    label: "Setup",
    children: [
      {
        label: "System's Picklist",
        children: [
          { label: "User Types", path: "/user_type" },
          { label: "Crew Positions", path: "/position" },
          { label: "Vessel Types", path: "/vessel_type" },
          { label: "Engine Types", path: "/engine_type" },
          { label: "Vessel Flag", path: "/vessel_flag" },
          { label: "Vessel Trade Area", path: "/vessel_trade_area" },
          { label: "Hull Construction", path: "/hull_construction" },
          { label: "Cargo", path: "/cargo" },
          { label: "OPEX Chart", path: "/opex_chart" },
          { label: "CBA, Union", path: "/cba_union" },
          { label: "Observation's Categories", path: "/observation_category" },
          { label: "Units (Inventory)", path: "/inventory_units" },
          { label: "Form's Reference No.", path: "/reports_footer_header" },
        ],
      },
      {
        label: "Logs",
        children: [
          { label: "Import", path: "/import" },
          { label: "Export", path: "/export" },
          { label: "System Update", path: "/system_update" },
          { label: "Database Update", path: "/database_update" },
          { label: "Shore", path: "/logs" },
          { label: "Vessel", path: "/logs/vessel_logs" },
        ],
      },
      { label: "Principals", path: "/principal" },
      { label: "Vessels", path: "/vesselinfo" },
      { label: "Vessel's Account Settings", path: "/vessel_settings" },
      {
        label: "PMS",
        children: [
          { label: "Configuration", path: "/pms_setup_configuration" },
          { label: "Department", path: "/pms_setup_department" },
          { label: "Classifications", path: "/pms_setup_classification" },
          { label: "Components", path: "/pms_setup_equipment" },
          { label: "Parts", path: "/pms_setup_parts" },
          { label: "Activities", path: "/pms_setup_activities" },
          { label: "Running Hours", path: "/pms_setup_running_hours_equipments" },
        ],
      },
      {
        label: "Incident Report / HOR",
        children: [{ label: "List of Nature", path: "/incident_nature" }],
      },
      {
        label: "Risk Assessment (Vessel)",
        children: [
          { label: "Categories", path: "/risk_assessment_category" },
          { label: "Tasks", path: "/risk_assessment_operations" },
        ],
      },
      {
        label: "Risk Assessment (Shore)",
        children: [
          { label: "Categories", path: "/risk_assessment_category_shore" },
          { label: "Tasks", path: "/risk_assessment_operations_shore" },
        ],
      },
      { label: "VIQ", path: "/sire_viq" },
      {
        label: "Non-SIRE",
        children: [{ label: "Inspection Types", path: "/non_sire_inspection_type" }],
      },
      {
        label: "Committee Meeting",
        children: [
          { label: "Types", path: "/committee_meeting_type" },
          { label: "Topics", path: "/committee_meeting_topics" },
        ],
      },
      {
        label: "Drill",
        children: [
          { label: "Types of Drill", path: "/drill_type" },
          { label: "Lists of Drill", path: "/drill_list" },
        ],
      },
      {
        label: "Safety Manual",
        children: [
          { label: "Manuals", path: "/sms_chapter" },
          { label: "Procedures", path: "/sms_setup" },
          { label: "Forms/Posters", path: "/sms_forms" },
        ],
      },
      {
        label: "Vessel Documentation",
        children: [
          { label: "Document Type", path: "/vessel_document_type" },
          { label: "Document List", path: "/vessel_document_list" },
        ],
      },
      {
        label: "Company Documentation",
        children: [
          { label: "Document Type", path: "/company_document_type" },
          { label: "Document List", path: "/company_document_list" },
        ],
      },
      {
        label: "Address Book",
        children: [{ label: "Category", path: "/address_book_category" }],
      },
      {
        label: "Tasks",
        children: [
          { label: "Categories", path: "/task_categories" },
          { label: "Actions", path: "/task_actions" },
        ],
      },
    ],
  },
];

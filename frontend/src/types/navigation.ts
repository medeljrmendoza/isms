export interface NavLeaf {
  label: string;
  path: string;
}

export interface NavGroup {
  label: string;
  children: NavLeaf[];
}

export type NavChild = NavLeaf | NavGroup;

export interface NavItem {
  label: string;
  path?: string;
  children?: NavChild[];
}

export function isNavGroup(child: NavChild): child is NavGroup {
  return "children" in child;
}

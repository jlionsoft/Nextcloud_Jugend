import { createRouter, createWebHistory, type RouteRecordRaw } from 'vue-router'
import MainApp from './components/MainApp.vue'
import GroupsApp from './components/GroupsApp.vue'
import OrganizationApp from './components/OrganizationApp.vue'
import AccountingApp from './components/AccountingApp.vue'

export function getAppBaseUrl(): string {
	const suffixes = ['/organization', '/groups', '/accounting/manage', '/accounting/me']
	const pathname = window.location.pathname
	for (const suffix of suffixes) {
		if (pathname.endsWith(suffix) || pathname.endsWith(`${suffix}/`)) {
			return pathname.slice(0, pathname.length - suffix.length) + '/'
		}
	}
	return pathname.endsWith('/') ? pathname : `${pathname}/`
}

const routes: RouteRecordRaw[] = [
	{ path: '/', component: MainApp },
	{ path: '/organization', component: OrganizationApp },
	{ path: '/groups', component: GroupsApp },
	{ path: '/accounting/manage', component: AccountingApp, props: { mode: 'manage' } },
	{ path: '/accounting/me', component: AccountingApp, props: { mode: 'personal' } },
]

export function createAppRouter() {
	return createRouter({
		history: createWebHistory(getAppBaseUrl()),
		routes,
	})
}

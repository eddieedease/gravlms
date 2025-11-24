import { Routes } from '@angular/router';
import { Landing } from './components/landing/landing';
import { Login } from './components/login/login';
import { Admin } from './components/admin/admin';
import { Editor } from './components/editor/editor';
import { DashboardComponent } from './components/dashboard/dashboard';
import { CourseViewerComponent } from './components/course-viewer/course-viewer';

import { authGuard } from './guards/auth.guard';

export const routes: Routes = [
    { path: '', component: Landing },
    { path: 'login', component: Login },
    { path: 'dashboard', component: DashboardComponent, canActivate: [authGuard] },
    { path: 'learn/:courseId', component: CourseViewerComponent, canActivate: [authGuard] },
    { path: 'admin', component: Admin, canActivate: [authGuard] },
    { path: 'editor', component: Editor, canActivate: [authGuard] },
    { path: '**', redirectTo: '' }
];

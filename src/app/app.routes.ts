import { Routes } from '@angular/router';
import { Landing } from './components/landing/landing';
import { Login } from './components/login/login';
import { Admin } from './components/admin/admin';
import { Editor } from './components/editor/editor';
import { DashboardComponent } from './components/dashboard/dashboard';
import { CourseViewerComponent } from './components/course-viewer/course-viewer';

import { authGuard } from './guards/auth.guard';
import { roleGuard } from './guards/role.guard';

import { ForgotPasswordComponent } from './components/forgot-password/forgot-password.component';
import { ResetPasswordComponent } from './components/reset-password/reset-password.component';

import { ResultsComponent } from './components/results/results.component';

export const routes: Routes = [
    { path: '', component: Landing },
    { path: 'login/:tenant', component: Login },
    { path: 'login', component: Login },
    { path: 'forgot-password', component: ForgotPasswordComponent },
    { path: 'reset-password', component: ResetPasswordComponent },
    { path: 'dashboard', component: DashboardComponent, canActivate: [authGuard] },
    { path: 'learn/:courseId', component: CourseViewerComponent, canActivate: [authGuard] },
    { path: 'editor', component: Editor, canActivate: [authGuard, roleGuard(['admin', 'editor'])] },
    { path: 'results', component: ResultsComponent, canActivate: [authGuard, roleGuard(['admin', 'monitor'])] },
    {
        path: 'portfolio',
        loadComponent: () => import('./components/portfolio/portfolio').then(m => m.PortfolioComponent),
        canActivate: [authGuard]
    },
    { path: 'admin', component: Admin, canActivate: [authGuard, roleGuard(['admin'])] },
    {
        path: 'assessments',
        loadComponent: () => import('./components/assessor-dashboard/assessor-dashboard').then(m => m.AssessorDashboardComponent),
        canActivate: [authGuard]
    },
    { path: '', redirectTo: 'login', pathMatch: 'full' },
    { path: '**', redirectTo: 'login' }
];

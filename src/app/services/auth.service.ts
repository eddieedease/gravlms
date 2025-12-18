import { Injectable, signal } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Router } from '@angular/router';
import { tap } from 'rxjs/operators';
import { ConfigService } from './config.service';

@Injectable({
    providedIn: 'root'
})
export class AuthService {
    private apiUrl: string;
    currentUser = signal<any>(null);
    isLtiMode = signal<boolean>(false);
    ltiCourseId = signal<number | null>(null);

    constructor(private http: HttpClient, private router: Router, private config: ConfigService) {
        this.apiUrl = this.config.apiUrl;
        const token = localStorage.getItem('token');
        const user = localStorage.getItem('user');
        if (token && user) {
            const userData = JSON.parse(user);
            this.currentUser.set(userData);
            this.isLtiMode.set(userData.lti_mode || false);
            this.ltiCourseId.set(userData.lti_course_id || null);
        }
    }

    login(credentials: any) {
        return this.http.post<any>(`${this.apiUrl}/login`, credentials).pipe(
            tap(response => {
                if (response.status === 'success') {
                    localStorage.setItem('token', response.token);
                    localStorage.setItem('user', JSON.stringify(response.user));
                    this.currentUser.set(response.user);
                    this.isLtiMode.set(response.user.lti_mode || false);
                    this.ltiCourseId.set(response.user.lti_course_id || null);
                }
            })
        );
    }

    logout() {
        localStorage.removeItem('token');
        localStorage.removeItem('user');
        this.currentUser.set(null);
        this.isLtiMode.set(false);
        this.ltiCourseId.set(null);
        this.router.navigate(['/login']);
    }

    getToken() {
        return localStorage.getItem('token');
    }

    isAuthenticated() {
        return !!this.getToken();
    }

    isAdmin() {
        const user = this.currentUser();
        return user && user.role === 'admin';
    }

    isEditor() {
        const user = this.currentUser();
        return user && (user.role === 'admin' || user.role === 'editor');
    }

    // Note: Monitor capability is dynamic (group assignment), but this checks the explicit role
    // For checking access to "Results" page, we might need a more permissive check or just let backend 403.
    // However, we want to show the specific nav item. The backend says if you are a monitor for ANY group you can see results.
    // We don't have that info in the minimal user object in localstorage unless we fetch it.
    // For now, let's assume 'monitor' role users AND admins have explicit access.
    // The "capability" monitors (editors who are monitors) might not see the "Results" link unless we update the profile data to include "is_monitor" flag?
    // Let's stick to simple Role checks for main nav, and maybe a separate API call or expanded token to know if they have monitor access.
    // Or just "Results" is visible to everyone but 403s? No, annoying.
    // Let's rely on the user.role for now, and dealing with 'Hybrid' monitors (Editors assigned as monitors) is a bit tricky without extra data.
    // Let's assume if you are an Editor you MIGHT be a monitor, so we might show it?
    // Or simpler: Anyone who is NOT just a 'viewer' can try to click Results?
    hasPrivilegedAccess() {
        const user = this.currentUser();
        return user && ['admin', 'editor', 'monitor'].includes(user.role);
    }

    forgotPassword(email: string) {
        return this.http.post(`${this.apiUrl}/forgot-password`, { email });
    }

    resetPassword(token: string, password: string) {
        return this.http.post(`${this.apiUrl}/reset-password`, { token, password });
    }
}

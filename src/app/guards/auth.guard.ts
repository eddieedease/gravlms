import { inject } from '@angular/core';
import { CanActivateFn, Router } from '@angular/router';
import { AuthService } from '../services/auth.service';

export const authGuard: CanActivateFn = (route, state) => {
    const authService = inject(AuthService);
    const router = inject(Router);

    // Check for LTI token in URL
    const token = route.queryParams['token'];
    const tenant = route.queryParams['tenant'];

    if (token) {
        localStorage.setItem('token', token);
        if (tenant) {
            localStorage.setItem('tenantId', tenant);
        }

        // Decode user from token to hydrate session
        try {
            const base64Url = token.split('.')[1];
            const base64 = base64Url.replace(/-/g, '+').replace(/_/g, '/');
            const jsonPayload = decodeURIComponent(window.atob(base64).split('').map(function (c) {
                return '%' + ('00' + c.charCodeAt(0).toString(16)).slice(-2);
            }).join(''));
            const payload = JSON.parse(jsonPayload);

            if (payload.data) {
                localStorage.setItem('user', JSON.stringify(payload.data));
                authService.currentUser.set(payload.data);
                authService.isLtiMode.set(payload.data.lti_mode || false);
                authService.ltiCourseId.set(payload.data.lti_course_id || null);
            }
        } catch (e) {
            console.error('Failed to decode LTI token', e);
        }

        return true;
    }

    if (authService.isAuthenticated()) {
        return true;
    } else {
        router.navigate(['/login']);
        return false;
    }
};

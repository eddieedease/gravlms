import { inject } from '@angular/core';
import { CanActivateFn, Router } from '@angular/router';
import { AuthService } from '../services/auth.service';

export const roleGuard: (allowedRoles: string[]) => CanActivateFn = (allowedRoles: string[]) => {
    return (route, state) => {
        const authService = inject(AuthService);
        const router = inject(Router);

        const user = authService.currentUser();

        if (user && allowedRoles.includes(user.role)) {
            return true;
        }

        // If not authorized, redirect to dashboard (or login if no user)
        router.navigate(['/dashboard']);
        return false;
    };
};

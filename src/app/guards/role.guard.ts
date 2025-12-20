import { inject } from '@angular/core';
import { CanActivateFn, Router } from '@angular/router';
import { AuthService } from '../services/auth.service';

export const roleGuard = (allowedRoles: string[]) => {
    return () => {
        const auth = inject(AuthService);
        const router = inject(Router);
        const user = auth.currentUser();

        if (user) {
            // Special check for monitor "role" which is a capability, not just a role string
            if (allowedRoles.includes('monitor') && auth.isMonitor()) {
                return true;
            }

            if (allowedRoles.includes(user.role)) {
                return true;
            }
        }

        router.navigate(['/dashboard']);
        return false;
    };
};

import { HttpInterceptorFn, HttpErrorResponse } from '@angular/common/http';
import { catchError, throwError } from 'rxjs';
import { inject } from '@angular/core';
import { Router } from '@angular/router';

export const authInterceptor: HttpInterceptorFn = (req, next) => {
    // Avoid circular dependency by not injecting AuthService here
    // AuthService -> HttpClient -> AuthInterceptor -> AuthService
    const token = localStorage.getItem('token');
    const router = inject(Router);

    // Clone the request and add the authorization header if token exists
    const tenantId = localStorage.getItem('tenantId');
    let headers: any = {};

    if (token) {
        headers['Authorization'] = `Bearer ${token}`;
    }

    if (tenantId) {
        headers['X-Tenant-ID'] = tenantId;
    }

    req = req.clone({
        setHeaders: headers
    });

    return next(req).pipe(
        catchError((error: HttpErrorResponse) => {
            if (error.status === 401) {
                // Clear state manually as we can't call authService.logout() easily without circular dep
                localStorage.removeItem('token');
                localStorage.removeItem('user');
                router.navigate(['/login']);
            }
            return throwError(() => error);
        })
    );
};

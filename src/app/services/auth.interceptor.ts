import { HttpInterceptorFn, HttpErrorResponse } from '@angular/common/http';
import { catchError, throwError } from 'rxjs';
import { inject, Injector } from '@angular/core';
import { Router } from '@angular/router';
import { AuthService } from './auth.service';

export const authInterceptor: HttpInterceptorFn = (req, next) => {
    // Avoid circular dependency by not injecting AuthService here
    // AuthService -> HttpClient -> AuthInterceptor -> AuthService
    const token = localStorage.getItem('token');
    const router = inject(Router);
    const injector = inject(Injector);

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
                // Use Injector to get AuthService lazily to avoid circular dependency
                // and because inject() cannot be used inside the callback
                const authService = injector.get(AuthService);
                authService.logout();
            }
            return throwError(() => error);
        })
    );
};

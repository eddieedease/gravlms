import { Component, inject, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { AuthService } from '../../services/auth.service';
import { TranslateModule } from '@ngx-translate/core';

@Component({
    selector: 'app-reset-password',
    standalone: true,
    imports: [CommonModule, ReactiveFormsModule, RouterLink, TranslateModule],
    template: `
    <div class="flex min-h-full flex-col justify-center px-6 py-12 lg:px-8 bg-gray-50">
      <div class="sm:mx-auto sm:w-full sm:max-w-sm">
        <h2 class="mt-10 text-center text-2xl font-bold leading-9 tracking-tight text-gray-900">Set new password</h2>
      </div>

      <div class="mt-10 sm:mx-auto sm:w-full sm:max-w-sm card p-8">
        <form class="space-y-6" [formGroup]="form" (ngSubmit)="onSubmit()">
          <div>
            <label for="password" class="block text-sm font-medium leading-6 text-gray-900">New Password</label>
            <div class="mt-2">
              <input id="password" type="password" formControlName="password" required class="input-field">
            </div>
            <div *ngIf="form.get('password')?.touched && form.get('password')?.invalid" class="text-red-600 text-xs mt-1">
              Password is required (min 6 characters).
            </div>
          </div>

          <div>
            <label for="confirmPassword" class="block text-sm font-medium leading-6 text-gray-900">Confirm Password</label>
            <div class="mt-2">
              <input id="confirmPassword" type="password" formControlName="confirmPassword" required class="input-field">
            </div>
            <div *ngIf="form.hasError('mismatch') && form.get('confirmPassword')?.touched" class="text-red-600 text-xs mt-1">
              Passwords do not match.
            </div>
          </div>

          <div *ngIf="message" class="text-green-600 text-sm mt-2 bg-green-50 p-2 rounded border border-green-200">
            {{ message }}
          </div>

          <div *ngIf="errorMessage" class="text-red-600 text-sm mt-2 bg-red-50 p-2 rounded border border-red-200">
            {{ errorMessage }}
          </div>

          <div>
            <button type="submit" [disabled]="form.invalid || isLoading"
              class="flex w-full justify-center btn-primary disabled:opacity-50 disabled:cursor-not-allowed">
              {{ isLoading ? 'Resetting...' : 'Reset Password' }}
            </button>
          </div>

          <div class="text-sm text-center">
            <a routerLink="/login" class="font-semibold text-indigo-600 hover:text-indigo-500">Back to Login</a>
          </div>
        </form>
      </div>
    </div>
  `
})
export class ResetPasswordComponent implements OnInit {
    form: FormGroup;
    token: string = '';
    message: string = '';
    errorMessage: string = '';
    isLoading: boolean = false;
    private authService = inject(AuthService);
    private fb = inject(FormBuilder);
    private route = inject(ActivatedRoute);
    private router = inject(Router);

    constructor() {
        this.form = this.fb.group({
            password: ['', [Validators.required, Validators.minLength(6)]],
            confirmPassword: ['', Validators.required]
        }, { validators: this.passwordMatchValidator });
    }

    ngOnInit() {
        this.token = this.route.snapshot.queryParams['token'];
        if (!this.token) {
            this.errorMessage = 'Invalid or missing token.';
            this.form.disable();
        }
    }

    passwordMatchValidator(g: FormGroup) {
        return g.get('password')?.value === g.get('confirmPassword')?.value
            ? null : { mismatch: true };
    }

    onSubmit() {
        if (this.form.valid && this.token) {
            this.isLoading = true;
            this.message = '';
            this.errorMessage = '';

            this.authService.resetPassword(this.token, this.form.value.password).subscribe({
                next: (res: any) => {
                    this.message = res.message;
                    this.isLoading = false;
                    setTimeout(() => {
                        this.router.navigate(['/login']);
                    }, 3000);
                },
                error: (err) => {
                    this.errorMessage = err.error?.error || 'An error occurred. Please try again.';
                    this.isLoading = false;
                }
            });
        }
    }
}

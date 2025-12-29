import { Component, inject, OnInit, ChangeDetectorRef } from '@angular/core';
import { ReactiveFormsModule, FormBuilder, Validators } from '@angular/forms';
import { finalize, timeout } from 'rxjs/operators';
import { Router, RouterLink, ActivatedRoute } from '@angular/router';
import { AuthService } from '../../services/auth.service';
import { OrganisationService } from '../../services/organisation.service';

import { TranslateModule } from '@ngx-translate/core';

@Component({
  selector: 'app-login',
  imports: [ReactiveFormsModule, TranslateModule, RouterLink],
  templateUrl: './login.html',
  styleUrl: './login.css',
})
export class Login implements OnInit {
  private readonly fb = inject(FormBuilder);
  private readonly auth = inject(AuthService);
  private readonly router = inject(Router);
  private readonly route = inject(ActivatedRoute);
  private readonly cdr = inject(ChangeDetectorRef);
  public readonly orgService = inject(OrganisationService);
  errorMessage = '';

  form = this.fb.group({
    email: ['', [Validators.required, Validators.email]],
    password: ['', Validators.required]
  });

  isValidTenant = true;
  isLoading = true;

  ngOnInit() {
    this.route.paramMap.subscribe(params => {
      const urlTenant = params.get('tenant');
      const storedTenant = localStorage.getItem('tenantId');
      const isAuthenticated = this.auth.isAuthenticated();

      // Handle redirect logic based on tenant context
      if (isAuthenticated) {
        // checks if we are trying to access a different tenant than we are logged in to
        if (urlTenant && storedTenant && urlTenant !== storedTenant) {
          // User is logged in to a different tenant. Log them out to switch context.
          this.auth.clearSession();
          // Proceed to load the new tenant page...
        } else {
          // User is logged in and tenant matches (or no specific tenant in URL/Storage mismatch to care about?)
          // actually if urlTenant is null (generic login) but we are logged in, we should just go to dashboard
          this.router.navigate(['/dashboard']);
          return;
        }
      }

      if (urlTenant) {
        // 1. Store context
        localStorage.setItem('tenantId', urlTenant);

        // 2. Fetch Public Branding for this tenant
        console.log('Login: Start fetch for', urlTenant);
        this.orgService.getPublicSettings(urlTenant)
          .pipe(
            timeout(5000), // Force error after 5 seconds if backend hangs
            finalize(() => {
              console.log('Login: Finalize');
              setTimeout(() => {
                console.log('Login: Setting isLoading = false and detecting changes');
                this.isLoading = false;
                this.cdr.detectChanges();
              });
            })
          )
          .subscribe({
            next: (settings) => {
              this.orgService.settings.set(settings);
              this.isValidTenant = true;
            },
            error: (err) => {
              // Tenant likely doesn't exist or backend error
              console.error(err);
              this.isValidTenant = false;
              this.errorMessage = 'Organization not found. Please check your URL.';
            }
          });
      } else {
        // No tenant provided in URL
        this.isValidTenant = false;
        this.isLoading = false;
        this.errorMessage = 'Invalid Login URL. Please use the organization-specific link provided to you.';
      }
    });
  }

  onSubmit() {
    if (this.form.valid) {
      this.auth.login(this.form.value).subscribe({
        next: () => {
          this.router.navigate(['/']);
        },
        error: (err: any) => {
          this.errorMessage = 'Invalid credentials';
        }
      });
    }
  }
}

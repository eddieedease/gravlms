import { Component, inject, signal } from '@angular/core';
import { RouterOutlet, RouterLink, RouterLinkActive } from '@angular/router';
import { AuthService } from './services/auth.service';
import { ApiService } from './services/api.service';
import { AsyncPipe } from '@angular/common';
import { TranslateService, TranslateModule } from '@ngx-translate/core';
import { LoadingSpinnerComponent } from './components/loading-spinner/loading-spinner.component';
import { OrganisationService } from './services/organisation.service';

@Component({
  selector: 'app-root',
  imports: [RouterOutlet, RouterLink, RouterLinkActive, AsyncPipe, TranslateModule, LoadingSpinnerComponent],
  templateUrl: './app.html',
  styleUrl: './app.css'
})
export class App {
  protected readonly auth = inject(AuthService);
  private readonly apiService = inject(ApiService);
  private readonly translate = inject(TranslateService);
  // Inject to trigger global settings load (color, etc)
  private readonly orgService = inject(OrganisationService);

  protected readonly apiMessage$ = this.apiService.getTestMessage();

  constructor() {
    this.translate.addLangs(['en', 'nl']);
    this.translate.setDefaultLang('nl');
    this.translate.use('nl');

    // Load organization settings if tenant is known (e.g. from previous session)
    /*
    const tenantId = localStorage.getItem('tenantId');
    if (tenantId) {
      this.orgService.loadSettings(tenantId);
    }
    */
  }

  mobileMenuOpen = signal(false);

  toggleMobileMenu() {
    this.mobileMenuOpen.update(v => !v);
  }

  closeMobileMenu() {
    this.mobileMenuOpen.set(false);
  }

  changeLanguage(lang: string) {
    this.translate.use(lang);
  }
}


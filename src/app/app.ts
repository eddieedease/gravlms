import { Component, inject } from '@angular/core';
import { RouterOutlet, RouterLink, RouterLinkActive } from '@angular/router';
import { AuthService } from './services/auth.service';
import { ApiService } from './services/api.service';
import { AsyncPipe } from '@angular/common';
import { TranslateService, TranslateModule } from '@ngx-translate/core';
import { LoadingSpinnerComponent } from './components/loading-spinner/loading-spinner.component';

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

  protected readonly apiMessage$ = this.apiService.getTestMessage();

  constructor() {
    this.translate.addLangs(['en', 'nl']);
    this.translate.setDefaultLang('nl');
    this.translate.use('nl');
  }

  changeLanguage(lang: string) {
    this.translate.use(lang);
  }
}


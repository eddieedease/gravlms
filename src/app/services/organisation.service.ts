import { Injectable, signal, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, tap } from 'rxjs';
import { ConfigService } from './config.service';
import { AuthService } from './auth.service';
import { HttpHeaders } from '@angular/common/http';

export interface OrganisationSettings {
    id?: number;
    org_name: string;
    org_slogan: string;
    org_main_color: string;
    org_logo_url: string | null;
    org_header_image_url: string | null;
    org_email: string;
    news_message_enabled: boolean;
    news_message_content: string;
}

@Injectable({
    providedIn: 'root'
})
export class OrganisationService {
    private apiUrl: string;
    private uploadUrl: string;

    // Signal to hold current settings state for reactive UI updates
    settings = signal<OrganisationSettings>({
        org_name: 'My Organization',
        org_slogan: '',
        org_main_color: '#3b82f6',
        org_logo_url: null,
        org_header_image_url: null,
        org_email: '',
        news_message_enabled: false,
        news_message_content: ''
    });

    private authService = inject(AuthService); // Use inject for consistency or add to constructor

    constructor(private http: HttpClient, private config: ConfigService) {
        this.apiUrl = `${this.config.apiUrl}/organization`;
        this.uploadUrl = `${this.config.apiUrl}/uploads`;
        // this.loadSettings(); // Removed to prevent premature fetching before tenant ID is known
    }



    loadSettings(tenantId?: string) {
        let headers = new HttpHeaders();
        if (tenantId) {
            headers = headers.set('X-Tenant-ID', tenantId);
        }

        this.http.get<OrganisationSettings>(this.apiUrl, { headers }).subscribe({
            next: (data) => {
                this.settings.set(data);
                this.applyBranding(data);
            },
            error: (err) => {
                console.error('Failed to load organisation settings', err);
                // If we failed to load settings with a specific tenant ID, it implies the tenant is invalid
                if (tenantId) {
                    // We might want to handle this error in the component, but for now we reset settings
                    // Ideally we should return an Observable to the component so it can handle the error UI
                }
            }
        });
    }

    // New method that returns observable for the Login component to handle success/error
    getPublicSettings(tenantId: string): Observable<OrganisationSettings> {
        const headers = new HttpHeaders().set('X-Tenant-ID', tenantId);
        return this.http.get<OrganisationSettings>(this.apiUrl, { headers });
    }

    updateSettings(data: OrganisationSettings): Observable<any> {
        return this.http.post(`${this.apiUrl}/update`, data).pipe(
            tap(() => {
                this.settings.set(data);
                this.applyBranding(data);
            })
        );
    }

    uploadImage(file: File): Observable<{ filename: string, url: string }> {
        const formData = new FormData();
        formData.append('image', file);
        formData.append('type', 'organization');

        return this.http.post<{ filename: string, url: string }>(this.uploadUrl, formData);
    }

    private applyBranding(settings: OrganisationSettings) {
        if (settings.org_main_color) {
            document.documentElement.style.setProperty('--color-algolia-primary', settings.org_main_color);
            // This updates the Tailwind theme variable at runtime
        }
    }
}

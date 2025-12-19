import { Injectable } from '@angular/core';

declare global {
    interface Window {
        APP_CONFIG: {
            apiUrl: string;
        };
    }
}

@Injectable({
    providedIn: 'root'
})
export class ConfigService {
    private config = window.APP_CONFIG || { apiUrl: '/api' };

    get apiUrl(): string {
        return this.config.apiUrl;
    }
}

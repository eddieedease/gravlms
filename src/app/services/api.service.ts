import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { ConfigService } from './config.service';

@Injectable({
  providedIn: 'root'
})
export class ApiService {
  private apiUrl: string;

  constructor(private http: HttpClient, private config: ConfigService) {
    this.apiUrl = this.config.apiUrl;
  }

  // Email
  sendTestEmail(email: string): Observable<any> {
    return this.http.post(`${this.apiUrl}/admin/test-email`, { email });
  }

  getTestMessage(): Observable<any> {
    return this.http.get<any>(`${this.apiUrl}/test`);
  }

  // LTI Management
  getLtiPlatforms(): Observable<any[]> {
    return this.http.get<any[]>(`${this.apiUrl}/admin/lti/platforms`);
  }

  createLtiPlatform(platform: any): Observable<any> {
    return this.http.post<any>(`${this.apiUrl}/admin/lti/platforms`, platform);
  }

  getLtiTools(): Observable<any[]> {
    return this.http.get<any[]>(`${this.apiUrl}/admin/lti/tools`);
  }

  createLtiTool(tool: any): Observable<any> {
    return this.http.post<any>(`${this.apiUrl}/admin/lti/tools`, tool);
  }

  getLtiConsumerLaunchParams(toolId: number, courseId?: number): Observable<any> {
    return this.http.post<any>(`${this.apiUrl}/lti/consumer/launch`, { tool_id: toolId, course_id: courseId });
  }
}

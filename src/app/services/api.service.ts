import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';

@Injectable({
  providedIn: 'root'
})
export class ApiService {
  private apiUrl = 'http://localhost:8080/api';

  constructor(private http: HttpClient) { }

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

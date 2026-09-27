pipeline {
    agent { label 'linux-docker-amd64' }

    options {
        skipDefaultCheckout(true)
        disableConcurrentBuilds()
        timestamps()
        timeout(time: 4, unit: 'HOURS')
        buildDiscarder(logRotator(numToKeepStr: '20', artifactNumToKeepStr: '10'))
    }

    parameters {
        booleanParam(name: 'SCHEMA_BACKWARD_COMPATIBLE', defaultValue: false,
            description: 'Approve automatic image rollback after a migration has started only if old code works with the new schema.')
    }

    environment {
        IMAGE_REPOSITORY = 'ghcr.io/fikriislamyy/esign-saas'
        RELEASE_JOB      = 'true',
        DEPLOY_HOST      = 'ec2-user@13.61.11.156'
    }

    stages {
        stage('Checkout') {
            steps {
                deleteDir()
                checkout scm
                script {
                    env.RELEASE_SHA = sh(returnStdout: true, script: 'git rev-parse HEAD').trim()
                    if (!(env.RELEASE_SHA ==~ /^[0-9a-f]{40}$/)) {
                        error('Checkout did not produce a full Git SHA.')
                    }
                    currentBuild.displayName = "#${env.BUILD_NUMBER} ${env.RELEASE_SHA.take(12)}"
                }
            }
        }

        stage('Validate and test') {
            steps {
                sh 'bash scripts/ci/test.sh'
            }
        }

        stage('Build production image') {
            when { expression { env.RELEASE_JOB == 'true' && env.BRANCH_NAME == 'main' } }
            steps {
                sh '''#!/usr/bin/env bash
set -Eeuo pipefail
docker build --platform linux/amd64 -f docker/php/Dockerfile --build-arg "VCS_REF=$RELEASE_SHA" -t "$IMAGE_REPOSITORY:$RELEASE_SHA" .
docker run --rm --entrypoint test "$IMAGE_REPOSITORY:$RELEASE_SHA" -s /var/www/public/build/manifest.json
docker run --rm --entrypoint php "$IMAGE_REPOSITORY:$RELEASE_SHA" -r 'exit(extension_loaded("pdo_pgsql") && extension_loaded("redis") ? 0 : 1);'
'''
            }
        }

        stage('Publish immutable image') {
            when { expression { env.RELEASE_JOB == 'true' && env.BRANCH_NAME == 'main' } }
            steps {
                withCredentials([usernamePassword(credentialsId: 'esign-registry-push',
                    usernameVariable: 'REGISTRY_USER', passwordVariable: 'REGISTRY_TOKEN')]) {
                    sh '''#!/usr/bin/env bash
set -Eeuo pipefail
export DOCKER_CONFIG="$(mktemp -d)"
trap 'rm -rf "$DOCKER_CONFIG"' EXIT
printf '%s' "$REGISTRY_TOKEN" | docker login ghcr.io -u "$REGISTRY_USER" --password-stdin
docker push "$IMAGE_REPOSITORY:$RELEASE_SHA"
docker image inspect --format '{{index .RepoDigests 0}}' "$IMAGE_REPOSITORY:$RELEASE_SHA" > image-digest.txt
'''
                }
                script {
                    env.RELEASE_IMAGE = readFile('image-digest.txt').trim()
                    if (!(env.RELEASE_IMAGE ==~ /^ghcr\.io\/fikriislamyy\/esign-saas@sha256:[0-9a-f]{64}$/)) {
                        error('Registry did not return a trusted immutable digest.')
                    }
                    def compatible = params.SCHEMA_BACKWARD_COMPATIBLE ? 'true' : 'false'
                    writeFile(file: 'release.env', text: "APP_IMAGE=${env.RELEASE_IMAGE}\nRELEASE_SHA=${env.RELEASE_SHA}\nRELEASE_SEQUENCE=${env.BUILD_NUMBER}\nROLLBACK_COMPATIBLE=${compatible}\n")
                }
            }
        }

        stage('Approve production') {
            when { expression { env.RELEASE_JOB == 'true' && env.BRANCH_NAME == 'main' } }
            steps {
                script {
                    timeout(time: 2, unit: 'HOURS') {
                        input(message: "Deploy ${env.RELEASE_SHA} (${env.RELEASE_IMAGE})? Review migration compatibility first.",
                            ok: 'Deploy', submitter: 'release-operators')
                    }
                }
            }
        }

        stage('Deploy production') {
            when { expression { env.RELEASE_JOB == 'true' && env.BRANCH_NAME == 'main' } }
            steps {
                script {
                    def latest = sh(returnStdout: true, script: 'git ls-remote origin refs/heads/main').trim().tokenize()[0]
                    if (latest != env.RELEASE_SHA) {
                        error('A newer main revision exists. Build and approve that revision instead.')
                    }
                }
                withCredentials([file(credentialsId: 'esign-prod-known-hosts', variable: 'KNOWN_HOSTS')]) {
                    sshagent(credentials: ['esign-prod-ssh']) {
                        sh '''#!/usr/bin/env bash
set -Eeuo pipefail
: "${DEPLOY_HOST:?Configure DEPLOY_HOST on the trusted Jenkins release job}"
ssh_opts=(-o BatchMode=yes -o StrictHostKeyChecking=yes -o "UserKnownHostsFile=$KNOWN_HOSTS")
tar -cf - docker-compose.prod.yml docker/production/Caddyfile scripts/deploy release.env |
    ssh "${ssh_opts[@]}" "$DEPLOY_HOST" "mkdir -p /opt/esign/releases/$RELEASE_SHA && tar -xf - -C /opt/esign/releases/$RELEASE_SHA"
ssh "${ssh_opts[@]}" "$DEPLOY_HOST" "bash /opt/esign/releases/$RELEASE_SHA/scripts/deploy/deploy.sh $RELEASE_SHA"
'''
                    }
                }
            }
        }
    }

    post {
        always {
            junit allowEmptyResults: true, testResults: 'ci-results/*.xml'
            archiveArtifacts allowEmptyArchive: true, artifacts: 'ci-results/*.xml,release.env,image-digest.txt'
        }
    }
}
SHELL=bash

##########
# CONFIG #
##########
USER=chiarcos
TAG=0.22

##################
# BUILD and PUSH #
##################

all: 
	@if make push; then \
		echo ok 1>&2; \
	 else \
	 	echo "ERROR: pushing failed, make sure you're logged in, e.g., using " 1>&2;\
	 	echo "       $> docker login -u "${USER}" -p INSERT_PASSWORD_HERE docker.io" 1>&2;\
		exit 2;\
	fi;	 	

refresh:
	docker build --no-cache --tag ${USER}/teitok-docker:${TAG} .
	

build:
	@echo building ${USER}/teitok-docker:${TAG} 1>&2;
	docker build --tag ${USER}/teitok-docker:${TAG} .
	docker tag ${USER}/teitok-docker:${TAG} ${USER}/teitok-docker:latest

push: build
	docker push ${USER}/teitok-docker:${TAG}
	docker push ${USER}/teitok-docker:latest
